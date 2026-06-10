import {useEffect, useMemo, useState} from 'react'
import {createPortal} from 'react-dom'
import EmptyState from './EmptyState'
import {listPcapPackets} from '../lib/api'
import {formatBytes, formatDateTime, formatEndpoint} from '../lib/formatters'

const PACKET_PAGE_SIZE = 16
const FILTER_CACHE_TTL_MS = 1000 * 60 * 60 * 24

const DEFAULT_PACKET_FILTERS = {
    packetNumber: '',
    srcIp: '',
    dstIp: '',
    srcPort: '',
    dstPort: '',
    protocol: '',
    capturedLen: '',
    ttl: '',
    timestampFrom: '',
    timestampTo: '',
}

const DEFAULT_PACKET_ORDER = {field: 'packetNumber', direction: 'ASC'}

const EMPTY_LIST_STATE = {items: [], total: 0, isLoading: false, error: ''}

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
        localStorage.setItem(key, JSON.stringify({value, expiresAt: Date.now() + FILTER_CACHE_TTL_MS}))
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

function buildPacketQuery(filters, order) {
    return cleanQuery({
        packetNumber: filters.packetNumber,
        srcIp: filters.srcIp,
        dstIp: filters.dstIp,
        srcPort: filters.srcPort,
        dstPort: filters.dstPort,
        protocol: filters.protocol,
        capturedLen: filters.capturedLen,
        ttl: filters.ttl,
        timestamp: filters.timestampFrom && filters.timestampTo
            ? `${filters.timestampFrom};${filters.timestampTo}`
            : buildOperatorFilter(filters.timestampFrom ? '>=' : '<=', filters.timestampFrom || filters.timestampTo),
        orderBy: `${order.field} ${order.direction}`,
    })
}

function countActiveFilters(filters) {
    return Object.values(filters).filter((v) => v !== undefined && v !== null && v !== '').length
}

function formatTcpFlags(value) {
    if (value === undefined || value === null || value === '') return 'N/A'
    return `0x${Number(value).toString(16).toUpperCase()}`
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

function PacketListPanel({
                             token,
                             pcapId,
                             flowKey,
                             cacheKey,
                             onPacketClick,
                             onApiFailure,
                             panelClassName = 'panel',
                         }) {
    const cached = cacheKey ? readExpiringCache(`ibr:packet-list:${cacheKey}`) : null

    const [packetPage, setPacketPage] = useState(cached?.page ?? 1)
    const [packetFilters, setPacketFilters] = useState(cached?.filters ?? DEFAULT_PACKET_FILTERS)
    const [appliedPacketFilters, setAppliedPacketFilters] = useState(cached?.appliedFilters ?? DEFAULT_PACKET_FILTERS)
    const [packetOrder, setPacketOrder] = useState(cached?.order ?? DEFAULT_PACKET_ORDER)
    const [packetsState, setPacketsState] = useState(EMPTY_LIST_STATE)
    const [isPacketFiltersOpen, setIsPacketFiltersOpen] = useState(false)

    const packetTotalPages = Math.max(1, Math.ceil((packetsState.total ?? 0) / PACKET_PAGE_SIZE))
    const packetQuery = useMemo(
        () => buildPacketQuery(appliedPacketFilters, packetOrder),
        [appliedPacketFilters, packetOrder]
    )

    useEffect(() => {
        if (!cacheKey) return
        writeExpiringCache(`ibr:packet-list:${cacheKey}`, {
            page: packetPage,
            filters: packetFilters,
            appliedFilters: appliedPacketFilters,
            order: packetOrder,
        })
    }, [appliedPacketFilters, cacheKey, packetFilters, packetOrder, packetPage])

    useEffect(() => {
        if (!token || !pcapId) {
            setPacketsState(EMPTY_LIST_STATE)
            return
        }

        let ignore = false

        async function loadPackets() {
            setPacketsState((current) => ({...current, isLoading: true, error: ''}))

            try {
                const response = await listPcapPackets(token, {
                    pcapId,
                    flowKey: flowKey ?? undefined,
                    page: packetPage,
                    limit: PACKET_PAGE_SIZE,
                    extraQuery: packetQuery,
                })

                if (ignore) return

                setPacketsState({
                    items: response.entities ?? [],
                    total: response.total ?? 0,
                    isLoading: false,
                    error: '',
                })
            } catch (requestError) {
                if (ignore) return

                setPacketsState((current) => ({
                    ...current,
                    isLoading: false,
                    error: onApiFailure
                        ? onApiFailure(requestError, 'Não foi possível carregar os pacotes.')
                        : requestError.message,
                }))
            }
        }

        void loadPackets()

        return () => {
            ignore = true
        }
    }, [onApiFailure, packetPage, packetQuery, pcapId, flowKey, token])

    const activePacketFiltersCount = countActiveFilters(appliedPacketFilters)

    const handlePacketSort = (field) => {
        setPacketOrder((current) => ({
            field,
            direction: current.field === field && current.direction === 'ASC' ? 'DESC' : 'ASC',
        }))
        setPacketPage(1)
    }

    return (
        <section className={panelClassName}>
            <div className="panel__header">
                <div>
                    <span className="panel__eyebrow">Pacotes</span>
                    <h3>Sequência de transmissão</h3>
                </div>
                <div className="pagination-actions">
                    <button
                        className="button button--ghost"
                        type="button"
                        onClick={() => setIsPacketFiltersOpen(true)}
                    >
                        Filtros{activePacketFiltersCount ? ` (${activePacketFiltersCount})` : ''}
                    </button>
                    <button
                        className="button button--ghost"
                        disabled={packetPage <= 1 || packetsState.isLoading}
                        onClick={() => setPacketPage((current) => Math.max(1, current - 1))}
                    >
                        Anterior
                    </button>
                    <span className="table-muted">
                        {packetPage} / {packetTotalPages}
                    </span>
                    <button
                        className="button button--ghost"
                        disabled={packetPage >= packetTotalPages || packetsState.isLoading}
                        onClick={() => setPacketPage((current) => current + 1)}
                    >
                        Próxima
                    </button>
                </div>
            </div>

            {isPacketFiltersOpen ? createPortal((
                <div className="filter-modal" role="dialog" aria-modal="true" aria-labelledby="packet-filter-title">
                    <button
                        className="filter-modal__backdrop"
                        type="button"
                        aria-label="Fechar filtros"
                        onClick={() => setIsPacketFiltersOpen(false)}
                    />
                    <form
                        className="filter-modal__panel"
                        onSubmit={(event) => {
                            event.preventDefault()
                            setPacketPage(1)
                            setAppliedPacketFilters(packetFilters)
                            setIsPacketFiltersOpen(false)
                        }}
                    >
                        <div className="filter-modal__header">
                            <div>
                                <span className="panel__eyebrow">Filtros</span>
                                <h3 id="packet-filter-title">Pacotes</h3>
                            </div>
                            <button className="button button--ghost" type="button"
                                    onClick={() => setIsPacketFiltersOpen(false)}>
                                Fechar
                            </button>
                        </div>
                        <div className="filter-grid">
                            <label className="field filter-grid__date-field">
                                <span>Timestamp de</span>
                                <input
                                    type="datetime-local"
                                    value={packetFilters.timestampFrom}
                                    onChange={(e) => setPacketFilters((c) => ({...c, timestampFrom: e.target.value}))}
                                />
                            </label>
                            <label className="field filter-grid__date-field">
                                <span>Timestamp até</span>
                                <input
                                    type="datetime-local"
                                    value={packetFilters.timestampTo}
                                    onChange={(e) => setPacketFilters((c) => ({...c, timestampTo: e.target.value}))}
                                />
                            </label>
                            <label className="field">
                                <span>Número</span>
                                <input
                                    type="number"
                                    min="1"
                                    value={packetFilters.packetNumber}
                                    onChange={(e) => setPacketFilters((c) => ({...c, packetNumber: e.target.value}))}
                                    placeholder="#"
                                />
                            </label>
                            <label className="field">
                                <span>Origem</span>
                                <input
                                    value={packetFilters.srcIp}
                                    onChange={(e) => setPacketFilters((c) => ({...c, srcIp: e.target.value}))}
                                    placeholder="IP origem"
                                />
                            </label>
                            <label className="field">
                                <span>Destino</span>
                                <input
                                    value={packetFilters.dstIp}
                                    onChange={(e) => setPacketFilters((c) => ({...c, dstIp: e.target.value}))}
                                    placeholder="IP destino"
                                />
                            </label>
                            <label className="field">
                                <span>Porta origem</span>
                                <input
                                    type="number"
                                    min="0"
                                    max="65535"
                                    value={packetFilters.srcPort}
                                    onChange={(e) => setPacketFilters((c) => ({...c, srcPort: e.target.value}))}
                                    placeholder="Ex: 443"
                                />
                            </label>
                            <label className="field">
                                <span>Porta destino</span>
                                <input
                                    type="number"
                                    min="0"
                                    max="65535"
                                    value={packetFilters.dstPort}
                                    onChange={(e) => setPacketFilters((c) => ({...c, dstPort: e.target.value}))}
                                    placeholder="Ex: 80"
                                />
                            </label>
                            <label className="field">
                                <span>Protocolo</span>
                                <select
                                    value={packetFilters.protocol}
                                    onChange={(e) => setPacketFilters((c) => ({...c, protocol: e.target.value}))}
                                >
                                    <option value="">Todos</option>
                                    <option value="6">TCP</option>
                                    <option value="17">UDP</option>
                                    <option value="1">ICMP</option>
                                    <option value="58">ICMPv6</option>
                                </select>
                            </label>
                            <label className="field">
                                <span>Tamanho</span>
                                <input
                                    type="number"
                                    min="0"
                                    value={packetFilters.capturedLen}
                                    onChange={(e) => setPacketFilters((c) => ({...c, capturedLen: e.target.value}))}
                                    placeholder="bytes"
                                />
                            </label>
                            <label className="field">
                                <span>TTL</span>
                                <input
                                    type="number"
                                    min="0"
                                    value={packetFilters.ttl}
                                    onChange={(e) => setPacketFilters((c) => ({...c, ttl: e.target.value}))}
                                    placeholder="TTL"
                                />
                            </label>
                        </div>
                        <div className="filter-modal__actions">
                            <button
                                className="button button--ghost"
                                type="button"
                                onClick={() => {
                                    setPacketFilters(DEFAULT_PACKET_FILTERS)
                                    setAppliedPacketFilters(DEFAULT_PACKET_FILTERS)
                                    setPacketOrder(DEFAULT_PACKET_ORDER)
                                    setPacketPage(1)
                                    setIsPacketFiltersOpen(false)
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

            {packetsState.error ? (
                <p className="form-message form-message--error">{packetsState.error}</p>
            ) : null}

            {packetsState.isLoading ? (
                <p className="panel__feedback">Carregando pacotes...</p>
            ) : packetsState.items.length === 0 ? (
                <EmptyState
                    title="Nenhum pacote encontrado"
                    copy="Nenhum pacote encontrado com os filtros aplicados."
                />
            ) : (
                <div className="table-wrap">
                    <table className={`data-table${onPacketClick ? ' data-table--clickable' : ''}`}>
                        <thead>
                        <tr>
                            <th><SortButton field="packetNumber" label="#" order={packetOrder}
                                            onSort={handlePacketSort}/></th>
                            <th><SortButton field="timestamp" label="Timestamp" order={packetOrder}
                                            onSort={handlePacketSort}/></th>
                            <th><SortButton field="srcIp" label="Origem" order={packetOrder} onSort={handlePacketSort}/>
                            </th>
                            <th><SortButton field="dstIp" label="Destino" order={packetOrder}
                                            onSort={handlePacketSort}/></th>
                            <th><SortButton field="capturedLen" label="Tamanho" order={packetOrder}
                                            onSort={handlePacketSort}/></th>
                            <th><SortButton field="ttl" label="TTL" order={packetOrder} onSort={handlePacketSort}/></th>
                            <th>Flags/ICMP</th>
                            {onPacketClick ? <th></th> : null}
                        </tr>
                        </thead>
                        <tbody>
                        {packetsState.items.map((packet) => (
                            <tr
                                key={packet.id}
                                onClick={onPacketClick ? () => onPacketClick(packet) : undefined}
                            >
                                <td>{packet.packetNumber}</td>
                                <td>{formatDateTime(packet.timestamp)}</td>
                                <td>{formatEndpoint(packet.srcIp, packet.srcPort)}</td>
                                <td>{formatEndpoint(packet.dstIp, packet.dstPort)}</td>
                                <td>{formatBytes(packet.capturedLen)}</td>
                                <td>{packet.ttl ?? 'N/A'}</td>
                                <td>
                                    {packet.protocol === 6
                                        ? formatTcpFlags(packet.tcpFlags)
                                        : packet.icmpType !== null && packet.icmpType !== undefined
                                            ? `type ${packet.icmpType} / code ${packet.icmpCode ?? 0}`
                                            : 'N/A'}
                                </td>
                                {onPacketClick ? <td><span className="table-link">Inspecionar →</span></td> : null}
                            </tr>
                        ))}
                        </tbody>
                    </table>
                </div>
            )}
        </section>
    )
}

export default PacketListPanel
