import {useEffect, useMemo, useState} from 'react'
import {createPortal} from 'react-dom'
import {
    Area,
    AreaChart,
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    Legend,
    Pie,
    PieChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts'
import EmptyState from '../components/EmptyState'
import MetricCard from '../components/MetricCard'
import PacketListPanel from '../components/PacketListPanel'
import StatusBadge from '../components/StatusBadge'
import {getPcapStats, listPcapFlows} from '../lib/api'
import {
    formatBytes,
    formatDateParts,
    formatDateTime,
    formatEndpoint,
    formatRelativePercent,
    getCaptureStatus,
    getCaptureVisibility,
    getFileLabel,
    getProtocolLabel,
} from '../lib/formatters'

const PROTOCOL_NAMES = {
    0: 'HOPOPT', 1: 'ICMP', 2: 'IGMP', 6: 'TCP', 17: 'UDP',
    41: 'IPv6', 47: 'GRE', 50: 'ESP', 58: 'ICMPv6', 255: 'Other',
}

const PROTOCOL_COLORS = [
    '#4596b6', '#f4bc5f', '#93e5b2', '#c67edc',
    '#e07878', '#5ec9a8', '#e0a050', '#7893a3',
]

const CHART_TOOLTIP_STYLE = {
    background: 'rgba(6, 15, 22, 0.96)',
    border: '1px solid rgba(127, 155, 173, 0.22)',
    borderRadius: '10px',
    color: '#dfe9ef',
    fontSize: '0.82rem',
}

const CHART_AXIS_STYLE = {fill: '#7893a3', fontSize: '0.75rem'}
const CHART_GRID = {strokeDasharray: '3 3', stroke: 'rgba(127, 155, 173, 0.1)'}

function ChartPanel({eyebrow, title, children, isEmpty}) {
    return (
        <section className="panel chart-panel">
            <div className="panel__header">
                <div>
                    <span className="panel__eyebrow">{eyebrow}</span>
                    <h3>{title}</h3>
                </div>
            </div>
            {isEmpty ? (
                <p className="panel__feedback">Sem dados suficientes.</p>
            ) : children}
        </section>
    )
}

const FLOW_PAGE_SIZE = 12
const FILTER_CACHE_TTL_MS = 1000 * 60 * 60 * 24
const DEFAULT_FLOW_FILTERS = {
    srcIp: '',
    dstIp: '',
    srcPort: '',
    dstPort: '',
    protocol: '',
    startTimestampFrom: '',
    endTimestampTo: '',
}

const DEFAULT_FLOW_ORDER = {field: 'packetCount', direction: 'DESC'}

const EMPTY_LIST_STATE = {
    items: [],
    total: 0,
    isLoading: false,
    error: '',
}

function readExpiringCache(key) {
    try {
        const raw = localStorage.getItem(key)
        if (!raw) return null

        const parsed = JSON.parse(raw)
        if (!parsed?.expiresAt || parsed.expiresAt < Date.now()) {
            localStorage.removeItem(key)
            return null
        }

        return parsed.value ?? null
    } catch {
        return null
    }
}

function writeExpiringCache(key, value) {
    try {
        localStorage.setItem(key, JSON.stringify({
            value,
            expiresAt: Date.now() + FILTER_CACHE_TTL_MS,
        }))
    } catch {
        // localStorage can be unavailable in restricted browser contexts.
    }
}

function cleanQuery(values) {
    return Object.fromEntries(
        Object.entries(values).filter(([, value]) => value !== undefined && value !== null && value !== '')
    )
}

function buildOperatorFilter(operator, value) {
    return value ? `operator[${operator}];values:${value}` : ''
}

function buildFlowQuery(filters, order) {
    return cleanQuery({
        srcIp: filters.srcIp,
        dstIp: filters.dstIp,
        srcPort: filters.srcPort,
        dstPort: filters.dstPort,
        protocol: filters.protocol,
        startTimestamp: buildOperatorFilter('>=', filters.startTimestampFrom),
        endTimestamp: buildOperatorFilter('<=', filters.endTimestampTo),
        orderBy: `${order.field} ${order.direction}`,
    })
}

function countActiveFilters(filters) {
    return Object.values(filters).filter((value) => value !== undefined && value !== null && value !== '').length
}

function SortButton({field, label, order, onSort}) {
    const isActive = order.field === field
    const indicator = isActive ? (order.direction === 'ASC' ? ' ↑' : ' ↓') : ''

    return (
        <button className="table-sort-button" type="button" onClick={() => onSort(field)}>
            {label}{indicator}
        </button>
    )
}

function DateDetailCard({label, value}) {
    const {date, time} = formatDateParts(value)

    return (
        <div className="detail-card">
            <span>{label}</span>
            <strong className="detail-card__datetime">
                {date}
                {time ? <span className="detail-card__time">{time}</span> : null}
            </strong>
        </div>
    )
}

function CaptureDetailPage({
                               token,
                               capture,
                               isLoading,
                               error,
                               activeTab,
                               captureId: routeCaptureId,
                               onNavigate,
                               onRetryProcessing,
                               onApiFailure,
                               flowListState,
                               onFlowListStateChange,
                           }) {
    const flowCacheId = routeCaptureId ?? capture?.id
    const initialFlowState = flowListState ?? readExpiringCache(`ibr:flow-list:${flowCacheId}`) ?? {}
    const [flowFilters, setFlowFilters] = useState(initialFlowState.filters ?? DEFAULT_FLOW_FILTERS)
    const [appliedFlowFilters, setAppliedFlowFilters] = useState(initialFlowState.appliedFilters ?? DEFAULT_FLOW_FILTERS)
    const [flowOrder, setFlowOrder] = useState(initialFlowState.order ?? DEFAULT_FLOW_ORDER)
    const [flowPage, setFlowPage] = useState(initialFlowState.page ?? 1)
    const [flowsState, setFlowsState] = useState(EMPTY_LIST_STATE)
    const [isFlowFiltersOpen, setIsFlowFiltersOpen] = useState(false)
    const [stats, setStats] = useState(null)

    const pcap = capture?.pcap ?? null
    const pcapId = pcap?.id ?? null
    const flowTotalPages = Math.max(1, Math.ceil((flowsState.total ?? 0) / FLOW_PAGE_SIZE))
    const flowQuery = useMemo(
        () => buildFlowQuery(appliedFlowFilters, flowOrder),
        [appliedFlowFilters, flowOrder]
    )

    useEffect(() => {
        if (!pcapId || !onFlowListStateChange) {
            return
        }

        onFlowListStateChange(String(flowCacheId), {
            page: flowPage,
            filters: flowFilters,
            appliedFilters: appliedFlowFilters,
            order: flowOrder,
        })
        writeExpiringCache(`ibr:flow-list:${flowCacheId}`, {
            page: flowPage,
            filters: flowFilters,
            appliedFilters: appliedFlowFilters,
            order: flowOrder,
        })
    }, [appliedFlowFilters, flowCacheId, flowFilters, flowOrder, flowPage, onFlowListStateChange, pcapId])

    useEffect(() => {
        if (!token || !pcapId) {
            return
        }

        let ignore = false

        async function loadFlows() {
            setFlowsState((current) => ({...current, isLoading: true, error: ''}))

            try {
                const response = await listPcapFlows(token, {
                    pcapId,
                    page: flowPage,
                    limit: FLOW_PAGE_SIZE,
                    extraQuery: flowQuery,
                })

                if (ignore) return

                setFlowsState({
                    items: response.entities ?? [],
                    total: response.total ?? 0,
                    isLoading: false,
                    error: '',
                })
            } catch (requestError) {
                if (ignore) return

                setFlowsState((current) => ({
                    ...current,
                    isLoading: false,
                    error: onApiFailure
                        ? onApiFailure(requestError, 'Não foi possível carregar os flows.')
                        : requestError.message,
                }))
            }
        }

        void loadFlows()

        return () => {
            ignore = true
        }
    }, [flowPage, flowQuery, onApiFailure, pcapId, token])

    useEffect(() => {
        if (!token || !routeCaptureId || !pcapId) return

        let ignore = false

        getPcapStats(token, routeCaptureId)
            .then(data => {
                if (!ignore) setStats(data)
            })
            .catch(() => {
                if (!ignore) setStats(null)
            })

        return () => {
            ignore = true
        }
    }, [token, routeCaptureId, pcapId])

    if (isLoading) {
        return (
            <div className="page-grid">
                <section className="panel panel--wide">
                    <p className="panel__feedback">Carregando detalhes da captura...</p>
                </section>
            </div>
        )
    }

    if (error) {
        return (
            <div className="page-grid">
                <section className="panel panel--wide">
                    <p className="form-message form-message--error">{error}</p>
                </section>
            </div>
        )
    }

    if (!capture) {
        return (
            <div className="page-grid">
                <section className="panel panel--wide">
                    <EmptyState
                        title="Captura não localizada"
                        copy="Volte para a listagem e selecione outro registro."
                        actionLabel="Voltar para capturas"
                        onAction={() => onNavigate('/captures')}
                    />
                </section>
            </div>
        )
    }

    const status = getCaptureStatus(capture.status)
    const visibility = getCaptureVisibility(capture.visibility)
    const captureId = capture.id
    const headerDetected = Boolean(pcap?.header?.id)
    const canRetry = Number(capture.status) === 5
    const activeFlowFiltersCount = countActiveFilters(appliedFlowFilters)
    const handleFlowSort = (field) => {
        setFlowOrder((current) => ({
            field,
            direction: current.field === field && current.direction === 'ASC' ? 'DESC' : 'ASC',
        }))
        setFlowPage(1)
    }

    return (
        <div className="page-grid">
            <section className="capture-header">
                <button className="breadcrumb-back" onClick={() => onNavigate('/captures')}>
                    ← Capturas
                </button>
                <div className="capture-header__meta">
                    <div className="capture-header__meta-info">
                        <span>{formatBytes(capture.fileSize)}</span>
                        {capture.uploadedAt ? (
                            <span className="capture-header__meta-sep">·</span>
                        ) : null}
                        {capture.uploadedAt ? (
                            <span>Enviado em {formatDateTime(capture.uploadedAt)}</span>
                        ) : null}
                    </div>
                    <span className="capture-header__key">{capture.key}</span>
                    <StatusBadge status={status}/>
                    {canRetry ? (
                        <button className="button button--ghost" onClick={() => onRetryProcessing(capture)}>
                            Reprocessar
                        </button>
                    ) : null}
                </div>
            </section>

            <nav className="tab-bar">
                <button
                    className={`tab-bar__item${activeTab === 'overview' ? ' is-active' : ''}`}
                    onClick={() => onNavigate(`/captures/${captureId}`)}
                >
                    Visão Geral
                </button>
                <button
                    className={`tab-bar__item${activeTab === 'flows' ? ' is-active' : ''}`}
                    onClick={() => onNavigate(`/captures/${captureId}/flows`)}
                >
                    Flows{pcap?.flowsTotal ? ` · ${pcap.flowsTotal}` : ''}
                </button>
                <button
                    className={`tab-bar__item${activeTab === 'packets' ? ' is-active' : ''}`}
                    onClick={() => onNavigate(`/captures/${captureId}/packets`)}
                >
                    Pacotes{pcap?.packetsTotal ? ` · ${pcap.packetsTotal}` : ''}
                </button>
            </nav>

            {activeTab === 'overview' ? (
                <>
                    <section className="metrics-grid">
                        <MetricCard label="Progresso" value={formatRelativePercent(capture.processed)}
                                    hint="Progresso do parsing" accent="amber"/>
                        <MetricCard label="Pacotes" value={pcap?.packetsTotal ?? 0} hint="Total de pacotes"
                                    accent="cyan"/>
                        <MetricCard label="Flows" value={pcap?.flowsTotal ?? 0} hint="Fluxos direcionais"
                                    accent="green"/>
                        <MetricCard label="Bytes capturados"
                                    value={formatBytes(pcap?.capturedBytes ?? capture.fileSize)}
                                    hint="Bytes capturados" accent="red"/>
                    </section>

                    {stats ? (
                        <div className="pcap-charts-grid">
                            <ChartPanel eyebrow="Flows" title="Protocolos" isEmpty={!stats.protocols?.length}>
                                <ResponsiveContainer width="100%" height={220}>
                                    <PieChart>
                                        <Pie
                                            data={stats.protocols.map(p => ({
                                                ...p,
                                                name: PROTOCOL_NAMES[p.protocol] ?? `Proto ${p.protocol}`,
                                            }))}
                                            cx="50%"
                                            cy="50%"
                                            innerRadius={55}
                                            outerRadius={85}
                                            paddingAngle={2}
                                            dataKey="packets"
                                            nameKey="name"
                                        >
                                            {stats.protocols.map((p, i) => (
                                                <Cell
                                                    key={p.protocol}
                                                    fill={PROTOCOL_COLORS[i % PROTOCOL_COLORS.length]}
                                                />
                                            ))}
                                        </Pie>
                                        <Tooltip
                                            contentStyle={CHART_TOOLTIP_STYLE}
                                            formatter={(v, name) => [v.toLocaleString('pt-BR'), name]}
                                        />
                                        <Legend
                                            iconType="circle"
                                            iconSize={8}
                                            wrapperStyle={{fontSize: '0.78rem', color: '#a9bcc8'}}
                                        />
                                    </PieChart>
                                </ResponsiveContainer>
                            </ChartPanel>

                            <ChartPanel eyebrow="IPs" title="Top Talkers" isEmpty={!stats.topTalkers?.length}>
                                <ResponsiveContainer width="100%" height={220}>
                                    <BarChart
                                        layout="vertical"
                                        data={stats.topTalkers.slice(0, 6)}
                                        margin={{left: 8, right: 16, top: 4, bottom: 4}}
                                    >
                                        <CartesianGrid {...CHART_GRID} horizontal={false}/>
                                        <XAxis
                                            type="number"
                                            tick={CHART_AXIS_STYLE}
                                            tickFormatter={v => formatBytes(v)}
                                            width={60}
                                        />
                                        <YAxis
                                            type="category"
                                            dataKey="ip"
                                            tick={CHART_AXIS_STYLE}
                                            width={112}
                                        />
                                        <Tooltip
                                            contentStyle={CHART_TOOLTIP_STYLE}
                                            formatter={(v, name) => [
                                                name === 'bytes' ? formatBytes(v) : v.toLocaleString('pt-BR'),
                                                name === 'bytes' ? 'Bytes' : 'Pacotes',
                                            ]}
                                        />
                                        <Bar dataKey="bytes" fill="#4596b6" radius={[0, 4, 4, 0]} maxBarSize={14}/>
                                    </BarChart>
                                </ResponsiveContainer>
                            </ChartPanel>

                            <ChartPanel eyebrow="Pacotes" title="Distribuição de tamanho"
                                        isEmpty={!stats.packetSizes?.length}>
                                <ResponsiveContainer width="100%" height={220}>
                                    <BarChart
                                        data={stats.packetSizes}
                                        margin={{left: 0, right: 8, top: 4, bottom: 4}}
                                    >
                                        <CartesianGrid {...CHART_GRID} vertical={false}/>
                                        <XAxis dataKey="bucket" tick={CHART_AXIS_STYLE}/>
                                        <YAxis tick={CHART_AXIS_STYLE} tickFormatter={v => v.toLocaleString('pt-BR')}/>
                                        <Tooltip
                                            contentStyle={CHART_TOOLTIP_STYLE}
                                            formatter={(v) => [v.toLocaleString('pt-BR'), 'Pacotes']}
                                        />
                                        <Bar dataKey="count" fill="#f4bc5f" radius={[4, 4, 0, 0]} maxBarSize={40}/>
                                    </BarChart>
                                </ResponsiveContainer>
                            </ChartPanel>

                            <ChartPanel eyebrow="Timeline" title="Atividade por minuto"
                                        isEmpty={!stats.timeline?.length}>
                                <ResponsiveContainer width="100%" height={220}>
                                    <AreaChart
                                        data={stats.timeline}
                                        margin={{left: 0, right: 8, top: 4, bottom: 4}}
                                    >
                                        <defs>
                                            <linearGradient id="timelineGrad" x1="0" y1="0" x2="0" y2="1">
                                                <stop offset="5%" stopColor="#4596b6" stopOpacity={0.35}/>
                                                <stop offset="95%" stopColor="#4596b6" stopOpacity={0}/>
                                            </linearGradient>
                                        </defs>
                                        <CartesianGrid {...CHART_GRID} vertical={false}/>
                                        <XAxis
                                            dataKey="ts"
                                            tick={CHART_AXIS_STYLE}
                                            interval="preserveStartEnd"
                                        />
                                        <YAxis tick={CHART_AXIS_STYLE} tickFormatter={v => v.toLocaleString('pt-BR')}/>
                                        <Tooltip
                                            contentStyle={CHART_TOOLTIP_STYLE}
                                            formatter={(v) => [v.toLocaleString('pt-BR'), 'Pacotes']}
                                        />
                                        <Area
                                            type="monotone"
                                            dataKey="packets"
                                            stroke="#4596b6"
                                            strokeWidth={2}
                                            fill="url(#timelineGrad)"
                                        />
                                    </AreaChart>
                                </ResponsiveContainer>
                            </ChartPanel>
                        </div>
                    ) : null}

                    <section className="panel panel--wide">
                        <div className="panel__header">
                            <div>
                                <span className="panel__eyebrow">Arquivo</span>
                                <h3>Metadados da captura</h3>
                            </div>
                        </div>
                        <div className="detail-grid">
                            <div className="detail-card">
                                <span>Arquivo</span>
                                <strong>{getFileLabel(capture.file)}</strong>
                            </div>
                            <div className="detail-card">
                                <span>Visibilidade</span>
                                <strong>{visibility.label}</strong>
                            </div>
                            <DateDetailCard label="Criado em" value={capture.createdAt}/>
                            <DateDetailCard label="Upload confirmado em" value={capture.uploadedAt}/>
                            <DateDetailCard label="Início do parser" value={capture.processStartedAt}/>
                            <DateDetailCard label="Fim do parser" value={capture.processFinishedAt}/>
                            <DateDetailCard label="Início do PCAP" value={pcap?.startTimestamp}/>
                            <DateDetailCard label="Fim do PCAP" value={pcap?.endTimestamp}/>
                        </div>
                        {capture.processError ? (
                            <p className="form-message form-message--error">{capture.processError}</p>
                        ) : null}
                    </section>

                    <section className="panel">
                        <div className="panel__header">
                            <div>
                                <span className="panel__eyebrow">PCAP</span>
                                <h3>Header e protocolos</h3>
                            </div>
                        </div>
                        <div className="detail-grid">
                            <div className="detail-card">
                                <span>Header</span>
                                <strong>{headerDetected ? `Detectado (#${pcap.header.id})` : 'Aguardando parsing'}</strong>
                            </div>
                            <div className="detail-card">
                                <span>Checksum</span>
                                <strong>{pcap?.checksum ?? '—'}</strong>
                            </div>
                            <div className="detail-card">
                                <span>Tentativas de processamento</span>
                                <strong>{capture.processAttempts ?? 0}</strong>
                            </div>
                        </div>
                        {Array.isArray(pcap?.protocols) && pcap.protocols.length > 0 ? (
                            <div className="proto-tags">
                                {pcap.protocols.map((p) => (
                                    <span key={p} className="status-badge status-badge--muted">{p}</span>
                                ))}
                            </div>
                        ) : null}
                    </section>
                </>
            ) : null}

            {activeTab === 'packets' ? (
                !pcapId ? (
                    <section className="panel panel--wide">
                        <EmptyState
                            title="Captura ainda sem estrutura PCAP"
                            copy="Os pacotes ficam disponíveis depois que o worker conclui o parser."
                        />
                    </section>
                ) : (
                    <PacketListPanel
                        token={token}
                        pcapId={pcapId}
                        cacheKey={`pcap:${pcapId}`}
                        onPacketClick={(packet) => onNavigate(`/captures/${captureId}/packets/${packet.id}`)}
                        onApiFailure={onApiFailure}
                        panelClassName="panel panel--wide"
                    />
                )
            ) : null}

            {activeTab === 'flows' ? (
                <section className="panel panel--wide">
                    <div className="panel__header">
                        <div>
                            <span className="panel__eyebrow">Flows</span>
                            <h3>Flows</h3>
                        </div>
                        <div className="pagination-actions">
                            <button
                                className="button button--ghost"
                                type="button"
                                onClick={() => setIsFlowFiltersOpen(true)}
                            >
                                Filtros{activeFlowFiltersCount ? ` (${activeFlowFiltersCount})` : ''}
                            </button>
                            <button
                                className="button button--ghost"
                                disabled={flowPage <= 1 || flowsState.isLoading}
                                onClick={() => setFlowPage((current) => Math.max(1, current - 1))}
                            >
                                Anterior
                            </button>
                            <span className="table-muted">Página {flowPage} de {flowTotalPages}</span>
                            <button
                                className="button button--ghost"
                                disabled={flowPage >= flowTotalPages || flowsState.isLoading}
                                onClick={() => setFlowPage((current) => current + 1)}
                            >
                                Próxima
                            </button>
                        </div>
                    </div>

                    {isFlowFiltersOpen ? createPortal((
                        <div className="filter-modal" role="dialog" aria-modal="true"
                             aria-labelledby="flow-filter-title">
                            <button
                                className="filter-modal__backdrop"
                                type="button"
                                aria-label="Fechar filtros"
                                onClick={() => setIsFlowFiltersOpen(false)}
                            />
                            <form
                                className="filter-modal__panel"
                                onSubmit={(event) => {
                                    event.preventDefault()
                                    setFlowPage(1)
                                    setAppliedFlowFilters(flowFilters)
                                    setIsFlowFiltersOpen(false)
                                }}
                            >
                                <div className="filter-modal__header">
                                    <div>
                                        <span className="panel__eyebrow">Filtros</span>
                                        <h3 id="flow-filter-title">Flows</h3>
                                    </div>
                                    <button className="button button--ghost" type="button"
                                            onClick={() => setIsFlowFiltersOpen(false)}>
                                        Fechar
                                    </button>
                                </div>
                                <div className="filter-grid">
                                    <label className="field filter-grid__date-field">
                                        <span>Início a partir de</span>
                                        <input
                                            type="datetime-local"
                                            value={flowFilters.startTimestampFrom}
                                            onChange={(event) => setFlowFilters((current) => ({
                                                ...current,
                                                startTimestampFrom: event.target.value
                                            }))}
                                        />
                                    </label>
                                    <label className="field filter-grid__date-field">
                                        <span>Fim até</span>
                                        <input
                                            type="datetime-local"
                                            value={flowFilters.endTimestampTo}
                                            onChange={(event) => setFlowFilters((current) => ({
                                                ...current,
                                                endTimestampTo: event.target.value
                                            }))}
                                        />
                                    </label>
                                    <label className="field">
                                        <span>Origem</span>
                                        <input
                                            value={flowFilters.srcIp}
                                            onChange={(event) => setFlowFilters((current) => ({
                                                ...current,
                                                srcIp: event.target.value
                                            }))}
                                            placeholder="IP de origem"
                                        />
                                    </label>
                                    <label className="field">
                                        <span>Destino</span>
                                        <input
                                            value={flowFilters.dstIp}
                                            onChange={(event) => setFlowFilters((current) => ({
                                                ...current,
                                                dstIp: event.target.value
                                            }))}
                                            placeholder="IP de destino"
                                        />
                                    </label>
                                    <label className="field">
                                        <span>Porta origem</span>
                                        <input
                                            type="number"
                                            min="0"
                                            max="65535"
                                            value={flowFilters.srcPort}
                                            onChange={(event) => setFlowFilters((current) => ({
                                                ...current,
                                                srcPort: event.target.value
                                            }))}
                                            placeholder="Ex: 443"
                                        />
                                    </label>
                                    <label className="field">
                                        <span>Porta destino</span>
                                        <input
                                            type="number"
                                            min="0"
                                            max="65535"
                                            value={flowFilters.dstPort}
                                            onChange={(event) => setFlowFilters((current) => ({
                                                ...current,
                                                dstPort: event.target.value
                                            }))}
                                            placeholder="Ex: 80"
                                        />
                                    </label>
                                    <label className="field">
                                        <span>Protocolo</span>
                                        <select
                                            value={flowFilters.protocol}
                                            onChange={(event) => setFlowFilters((current) => ({
                                                ...current,
                                                protocol: event.target.value
                                            }))}
                                        >
                                            <option value="">Todos</option>
                                            <option value="6">TCP</option>
                                            <option value="17">UDP</option>
                                            <option value="1">ICMP</option>
                                            <option value="58">ICMPv6</option>
                                        </select>
                                    </label>
                                </div>
                                <div className="filter-modal__actions">
                                    <button
                                        className="button button--ghost"
                                        type="button"
                                        onClick={() => {
                                            setFlowFilters(DEFAULT_FLOW_FILTERS)
                                            setAppliedFlowFilters(DEFAULT_FLOW_FILTERS)
                                            setFlowOrder(DEFAULT_FLOW_ORDER)
                                            setFlowPage(1)
                                            setIsFlowFiltersOpen(false)
                                        }}
                                    >
                                        Limpar
                                    </button>
                                    <button className="button button--primary" type="submit">
                                        Aplicar filtros
                                    </button>
                                </div>
                            </form>
                        </div>
                    ), document.body) : null}

                    {flowsState.error ? <p className="form-message form-message--error">{flowsState.error}</p> : null}

                    {!pcapId ? (
                        <EmptyState
                            title="Captura ainda sem estrutura PCAP"
                            copy="Os flows ficam disponíveis depois que o worker conclui o parser."
                        />
                    ) : flowsState.isLoading ? (
                        <p className="panel__feedback">Carregando flows...</p>
                    ) : flowsState.items.length === 0 ? (
                        <EmptyState
                            title="Nenhum flow encontrado"
                            copy="A captura ainda pode estar em processamento ou os filtros não retornaram resultados."
                        />
                    ) : (
                        <div className="table-wrap">
                            <table className="data-table data-table--clickable">
                                <thead>
                                <tr>
                                    <th><SortButton field="srcIp" label="Origem" order={flowOrder}
                                                    onSort={handleFlowSort}/></th>
                                    <th><SortButton field="dstIp" label="Destino" order={flowOrder}
                                                    onSort={handleFlowSort}/></th>
                                    <th><SortButton field="protocol" label="Proto" order={flowOrder}
                                                    onSort={handleFlowSort}/></th>
                                    <th><SortButton field="packetCount" label="Pacotes" order={flowOrder}
                                                    onSort={handleFlowSort}/></th>
                                    <th><SortButton field="bytesTotal" label="Bytes" order={flowOrder}
                                                    onSort={handleFlowSort}/></th>
                                    <th><SortButton field="startTimestamp" label="Início" order={flowOrder}
                                                    onSort={handleFlowSort}/></th>
                                    <th><SortButton field="endTimestamp" label="Fim" order={flowOrder}
                                                    onSort={handleFlowSort}/></th>
                                    <th></th>
                                </tr>
                                </thead>
                                <tbody>
                                {flowsState.items.map((flow) => (
                                    <tr
                                        key={flow.id}
                                        onClick={() => onNavigate(`/captures/${captureId}/flows/${flow.id}`)}
                                    >
                                        <td>{formatEndpoint(flow.srcIp, flow.srcPort)}</td>
                                        <td>{formatEndpoint(flow.dstIp, flow.dstPort)}</td>
                                        <td>{getProtocolLabel(flow.protocol)}</td>
                                        <td>{flow.packetCount}</td>
                                        <td>{formatBytes(flow.bytesTotal)}</td>
                                        <td>{formatDateTime(flow.startTimestamp)}</td>
                                        <td>{formatDateTime(flow.endTimestamp)}</td>
                                        <td>
                                            <span className="table-link">Inspecionar →</span>
                                        </td>
                                    </tr>
                                ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            ) : null}
        </div>
    )
}

export default CaptureDetailPage
