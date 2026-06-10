import {Fragment, useEffect, useState} from 'react'
import {Circle, MapContainer, Marker, TileLayer, useMap} from 'react-leaflet'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import {executeEnrichmentFlow, getEnrichmentFlow} from '../lib/api'

const ENRICHMENT_STATUS = {
    1: {label: 'Pendente', tone: 'muted'},
    2: {label: 'Executando', tone: 'warning'},
    3: {label: 'Sucesso', tone: 'success'},
    4: {label: 'Erro', tone: 'danger'},
    5: {label: 'Ignorado', tone: 'muted'},
}

const ROLE_LABELS = {
    source: 'Origem',
    destination: 'Destino',
}

const DARK_TILE_URL = 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png'
const DARK_TILE_ATTRIBUTION = '&copy; <a href="https://carto.com/">CARTO</a>'

const dotIcon = L.divIcon({
    className: '',
    html: '<div class="geo-marker-dot"></div>',
    iconSize: [14, 14],
    iconAnchor: [7, 7],
})

function countryFlag(code) {
    if (!code || code.length !== 2) return ''
    return String.fromCodePoint(
        ...code.toUpperCase().split('').map(c => 0x1F1E0 + c.charCodeAt(0) - 65)
    )
}

function MapAutoCenter({lat, lng}) {
    const map = useMap()
    useEffect(() => {
        map.setView([lat, lng])
    }, [map, lat, lng])
    return null
}

function MapResizeHandler({lat, lng}) {
    const map = useMap()
    useEffect(() => {
        const container = map.getContainer()
        let rafId = null
        const observer = new ResizeObserver(() => {
            if (rafId) return
            rafId = requestAnimationFrame(() => {
                map.invalidateSize()
                map.setView([lat, lng], map.getZoom(), {animate: false})
                rafId = null
            })
        })
        observer.observe(container)
        return () => {
            observer.disconnect()
            if (rafId) cancelAnimationFrame(rafId)
        }
    }, [map, lat, lng])
    return null
}

function GeoMap({lat, lng, radius, city, country}) {
    const [ready, setReady] = useState(false)

    useEffect(() => {
        setReady(true)
    }, [])

    if (!ready) return <div className="geo-map geo-map--placeholder"/>

    return (
        <div className="geo-map">
            <MapContainer
                center={[lat, lng]}
                zoom={radius && radius > 200000 ? 4 : radius && radius > 50000 ? 6 : 9}
                scrollWheelZoom={false}
                zoomControl={true}
                attributionControl={false}
                style={{height: '100%', width: '100%', borderRadius: '12px'}}
            >
                <TileLayer url={DARK_TILE_URL} attribution={DARK_TILE_ATTRIBUTION}/>
                <MapAutoCenter lat={lat} lng={lng}/>
                <MapResizeHandler lat={lat} lng={lng}/>
                {radius != null && (
                    <Circle
                        center={[lat, lng]}
                        radius={radius}
                        pathOptions={{
                            color: 'rgba(244, 188, 95, 0.7)',
                            fillColor: 'rgba(244, 188, 95, 0.08)',
                            fillOpacity: 1,
                            weight: 1.5,
                        }}
                    />
                )}
                <Marker position={[lat, lng]} icon={dotIcon}/>
            </MapContainer>
            {(city || country) && (
                <div className="geo-map__label">
                    {[city, country].filter(Boolean).join(', ')}
                </div>
            )}
        </div>
    )
}

function AbuseScoreBar({score}) {
    const color = score > 50 ? '#f0aaaa' : score > 20 ? '#ffd98c' : '#93e5b2'
    const label = score > 50 ? 'Alto risco' : score > 20 ? 'Risco moderado' : 'Baixo risco'

    return (
        <div className="abuse-score">
            <div className="abuse-score__track">
                <div
                    className="abuse-score__fill"
                    style={{width: `${score}%`, background: color}}
                />
            </div>
            <span className="abuse-score__label" style={{color}}>
                {score}% &mdash; {label}
            </span>
        </div>
    )
}

function getIpPreview(results = []) {
    const preview = {geo: null, asn: null, rdns: null, abuse: null, coords: null, asnOrg: null}

    for (const r of results) {
        if (r.status !== 3 || !r.summary) continue
        const id = r.integration?.identifier

        if (id === 'maxmind_geolite2') {
            const s = r.summary
            if (s.country || s.city) {
                const flag = countryFlag(s.country)
                const parts = [flag, s.countryName ?? s.country, s.city].filter(Boolean)
                preview.geo = parts.join(' · ')
            }
            if (s.latitude != null && s.longitude != null) {
                preview.coords = {
                    lat: Number(s.latitude),
                    lng: Number(s.longitude),
                    radius: s.accuracyRadius != null ? s.accuracyRadius * 1000 : null,
                    city: s.city ?? null,
                    country: s.countryName ?? s.country ?? null,
                }
            }
        } else if (id === 'team_cymru_asn') {
            if (r.summary.asn && !preview.asn) {
                preview.asn = `AS${r.summary.asn}`
            }
        } else if (id === 'censys') {
            const as = r.summary.autonomousSystem
            if (as?.asn) {
                preview.asn = `AS${as.asn}${as.name ? ` · ${as.name}` : ''}`
            }
        } else if (id === 'shodan_internet_db') {
            if (r.summary.organization) {
                preview.asnOrg = r.summary.organization
            }
        } else if (id === 'rdns') {
            const hostnames = r.summary.hostnames ?? (r.summary.hostname ? [r.summary.hostname] : [])
            if (hostnames.length > 0) preview.rdns = hostnames[0]
        } else if (id === 'abuseipdb') {
            if (r.summary.abuseConfidenceScore != null) {
                preview.abuse = r.summary.abuseConfidenceScore
            }
        }
    }

    if (preview.asn && preview.asnOrg && !preview.asn.includes('·')) {
        preview.asn = `${preview.asn} · ${preview.asnOrg}`
    }

    return preview
}

function renderResultRows(identifier, summary) {
    if (!summary || typeof summary !== 'object') return []

    switch (identifier) {
        case 'maxmind_geolite2': {
            const rows = []
            if (summary.country) rows.push(['País', `${countryFlag(summary.country)} ${summary.countryName ?? ''} (${summary.country})`])
            if (summary.city) rows.push(['Cidade', summary.city])
            if (summary.subdivision && summary.subdivision !== summary.city) rows.push(['Região', summary.subdivision])
            if (summary.latitude != null && summary.longitude != null) {
                rows.push(['Coord.', `${Number(summary.latitude).toFixed(4)}, ${Number(summary.longitude).toFixed(4)}${summary.accuracyRadius ? ` ±${summary.accuracyRadius}km` : ''}`])
            }
            return rows
        }
        case 'team_cymru_asn': {
            const rows = []
            if (summary.asn) rows.push(['ASN', `AS${summary.asn}`])
            if (summary.prefix) rows.push(['Prefixo', summary.prefix])
            if (summary.countryCode) rows.push(['País', `${countryFlag(summary.countryCode)} ${summary.countryCode}`])
            if (summary.registry) rows.push(['Registry', summary.registry])
            return rows
        }
        case 'rdns': {
            const hostnames = summary.hostnames ?? (summary.hostname ? [summary.hostname] : [])
            return [['PTR', hostnames.length > 0 ? hostnames.slice(0, 3).join(', ') : 'Nenhum registro']]
        }
        case 'shodan_internet_db': {
            const rows = []
            const ports = summary.ports ?? []
            if (ports.length > 0) rows.push(['Portas', `${ports.slice(0, 8).join(', ')}${ports.length > 8 ? ` +${ports.length - 8}` : ''}`])
            if (summary.vulnsCount > 0) rows.push(['CVEs', String(summary.vulnsCount)])
            if (summary.organization) rows.push(['Org.', summary.organization])
            if (ports.length === 0 && !summary.vulnsCount) rows.push(['Portas', 'Nenhuma observada'])
            return rows
        }
        case 'abuseipdb': {
            const rows = []
            const score = summary.abuseConfidenceScore
            if (score != null) rows.push(['Score', `${score}%`, score > 50 ? 'enrich-danger' : score > 20 ? 'enrich-warning' : 'enrich-safe'])
            if (summary.totalReports != null) rows.push(['Reports', String(summary.totalReports)])
            if (summary.isp) rows.push(['ISP', summary.isp])
            if (summary.countryCode) rows.push(['País', `${countryFlag(summary.countryCode)} ${summary.countryCode}`])
            return rows
        }
        case 'censys': {
            const rows = []
            const as = summary.autonomousSystem
            if (as?.asn) rows.push(['AS', `AS${as.asn}${as.name ? ` · ${as.name}` : ''}`])
            if (summary.location?.country) rows.push(['País', `${countryFlag(summary.location.countryCode ?? '')} ${summary.location.country}`])
            if (summary.serviceCount > 0) rows.push(['Serviços', `${summary.serviceCount}`])
            const ports = summary.ports ?? []
            if (ports.length > 0) rows.push(['Portas', `${ports.slice(0, 6).join(', ')}${ports.length > 6 ? ` +${ports.length - 6}` : ''}`])
            return rows
        }
        default:
            return []
    }
}

function EnrichmentResultCard({result}) {
    const statusDef = ENRICHMENT_STATUS[result.status] ?? {label: 'Desconhecido', tone: 'muted'}
    const identifier = result.integration?.identifier
    const rows = result.status === 3 ? renderResultRows(identifier, result.summary) : []

    return (
        <div className="enrichment-result">
            <div className="enrichment-result__header">
                <span className="enrichment-result__name">{result.integration?.name ?? identifier}</span>
                <span className={`status-badge status-badge--${statusDef.tone}`}>{statusDef.label}</span>
            </div>
            {result.error ? (
                <span className="enrichment-result__meta">{result.error}</span>
            ) : null}
            {rows.length > 0 ? (
                <dl className="enrich-kv">
                    {rows.map(([dt, dd, cls]) => (
                        <Fragment key={dt}>
                            <dt>{dt}</dt>
                            <dd className={cls}>{dd}</dd>
                        </Fragment>
                    ))}
                </dl>
            ) : null}
            {result.fetchedAt ? (
                <span className="enrichment-result__meta">{result.fetchedAt}</span>
            ) : null}
        </div>
    )
}

function getOptionalCapabilitiesForTarget(capabilities = [], targetId) {
    const seen = new Map()

    for (const cap of capabilities) {
        if (cap.alwaysExecute) continue
        if (cap.target?.id !== targetId) continue

        const id = cap.integration?.identifier
        if (!id) continue

        if (!seen.has(id)) {
            seen.set(id, {integration: cap.integration, capable: false, reason: cap.reason})
        }

        if (cap.capable) {
            seen.get(id).capable = true
            seen.get(id).reason = null
        }
    }

    return [...seen.values()]
}

function IpEnrichmentCard({observation, capabilities, running, onExecuteProvider}) {
    const [expanded, setExpanded] = useState(false)

    const target = observation.target
    const results = observation.results ?? []
    const roles = observation.roles ?? []
    const preview = getIpPreview(results)
    const optional = getOptionalCapabilitiesForTarget(capabilities, target?.id)
    const hasResults = results.length > 0

    return (
        <div className="ip-card">
            <div className="ip-card__header">
                <div className="ip-card__role-badges">
                    {roles.map(role => (
                        <span key={role} className="status-badge status-badge--muted">
                            {ROLE_LABELS[role] ?? role}
                        </span>
                    ))}
                </div>
                <span className="ip-card__address">{target?.value ?? '—'}</span>
            </div>

            <div className="ip-card__preview">
                {preview.geo ? (
                    <div className="ip-card__preview-row">
                        <span className="ip-card__preview-icon">&#127757;</span>
                        <span>{preview.geo}</span>
                    </div>
                ) : null}
                {preview.asn ? (
                    <div className="ip-card__preview-row">
                        <span className="ip-card__preview-icon">&#128279;</span>
                        <span className="ip-card__asn">{preview.asn}</span>
                    </div>
                ) : null}
                {preview.rdns ? (
                    <div className="ip-card__preview-row">
                        <span className="ip-card__preview-icon">&#128269;</span>
                        <span className="ip-card__rdns">{preview.rdns}</span>
                    </div>
                ) : null}
                {preview.abuse != null ? (
                    <div className="ip-card__preview-row ip-card__preview-row--abuse">
                        <span className="ip-card__preview-icon">&#9888;&#65039;</span>
                        <AbuseScoreBar score={preview.abuse}/>
                    </div>
                ) : null}
            </div>

            {preview.coords ? (
                <GeoMap
                    lat={preview.coords.lat}
                    lng={preview.coords.lng}
                    radius={preview.coords.radius}
                    city={preview.coords.city}
                    country={preview.coords.country}
                />
            ) : null}

            {hasResults ? (
                <button
                    className="ip-card__toggle"
                    onClick={() => setExpanded(e => !e)}
                >
                    {expanded ? '▲ Ocultar detalhes' : '▼ Ver detalhes dos providers'}
                </button>
            ) : null}

            {expanded && hasResults ? (
                <div className="ip-card__details">
                    {results.map(result => (
                        <EnrichmentResultCard key={result.id} result={result}/>
                    ))}
                </div>
            ) : null}

            {optional.length > 0 ? (
                <div className="ip-card__optional">
                    <p className="ip-card__optional-label">Providers opcionais</p>
                    <div className="ip-card__optional-list">
                        {optional.map(item => {
                            const id = item.integration?.identifier
                            const isRunning = running.some(r => r.provider === id && r.targetId === target?.id)

                            return (
                                <div key={id} className="enrichment-provider-row">
                                    <div>
                                        <span className="enrichment-provider-row__name">{item.integration?.name}</span>
                                        {!item.capable && item.reason ? (
                                            <span
                                                className="enrichment-provider-row__reason">&nbsp;&mdash; {item.reason}</span>
                                        ) : null}
                                    </div>
                                    <button
                                        className="button button--ghost"
                                        disabled={!item.capable || isRunning}
                                        onClick={() => onExecuteProvider(id, target?.id)}
                                    >
                                        {isRunning ? 'Executando...' : 'Executar'}
                                    </button>
                                </div>
                            )
                        })}
                    </div>
                </div>
            ) : null}
        </div>
    )
}

function getMissingAutoProviders(data) {
    const hasSuccess = new Set()

    for (const obs of data.targets ?? []) {
        for (const result of obs.results ?? []) {
            if (result.status === 3) {
                hasSuccess.add(`${result.integration?.identifier}:${obs.target?.id}`)
            }
        }
    }

    const providers = new Set()

    for (const cap of data.capabilities ?? []) {
        if (!cap.alwaysExecute || !cap.capable) continue

        if (!hasSuccess.has(`${cap.integration?.identifier}:${cap.target?.id}`)) {
            providers.add(cap.integration?.identifier)
        }
    }

    return [...providers]
}

function EnrichmentPanel({token, flowId, onApiFailure}) {
    const [enrichmentState, setEnrichmentState] = useState({isLoading: false, data: null, error: '', running: []})

    useEffect(() => {
        setEnrichmentState({isLoading: false, data: null, error: '', running: []})

        if (!token || !flowId) return

        let ignore = false

        async function loadEnrichment() {
            setEnrichmentState(current => ({...current, isLoading: true}))

            try {
                const existingData = await getEnrichmentFlow(token, flowId)

                if (ignore) return

                setEnrichmentState(current => ({...current, data: existingData}))

                const missing = getMissingAutoProviders(existingData)

                if (missing.length === 0) {
                    setEnrichmentState(current => ({...current, isLoading: false}))
                    return
                }

                const updatedData = await executeEnrichmentFlow(token, flowId, missing)

                if (ignore) return

                setEnrichmentState({isLoading: false, data: updatedData, error: '', running: []})
            } catch (requestError) {
                if (ignore) return

                setEnrichmentState(current => ({
                    ...current,
                    isLoading: false,
                    error: onApiFailure
                        ? onApiFailure(requestError, 'Falha ao carregar enriquecimento.')
                        : requestError.message,
                }))
            }
        }

        void loadEnrichment()

        return () => {
            ignore = true
        }
    }, [flowId, token, onApiFailure])

    function handleExecuteProvider(identifier, targetId) {
        if (!token || !flowId || enrichmentState.running.some(r => r.provider === identifier && r.targetId === targetId)) return

        setEnrichmentState(current => ({
            ...current,
            running: [...current.running, {provider: identifier, targetId}],
            error: '',
        }))

        executeEnrichmentFlow(token, flowId, [identifier], [targetId])
            .then(data => {
                setEnrichmentState(current => ({
                    ...current,
                    data,
                    running: current.running.filter(r => !(r.provider === identifier && r.targetId === targetId)),
                }))
            })
            .catch(requestError => {
                setEnrichmentState(current => ({
                    ...current,
                    error: onApiFailure
                        ? onApiFailure(requestError, `Falha ao executar ${identifier}.`)
                        : requestError.message,
                    running: current.running.filter(r => !(r.provider === identifier && r.targetId === targetId)),
                }))
            })
    }

    const targets = enrichmentState.data?.targets ?? []
    const capabilities = enrichmentState.data?.capabilities ?? []

    return (
        <section className="panel flow-enrich-panel">
            <div className="panel__header">
                <div>
                    <span className="panel__eyebrow">Enriquecimento</span>
                    <h3>Dados dos IPs</h3>
                </div>
                {enrichmentState.data ? (
                    <button
                        className="button button--ghost"
                        disabled={enrichmentState.isLoading || enrichmentState.running.length > 0}
                        onClick={() => {
                            setEnrichmentState(current => ({...current, isLoading: true, error: ''}))
                            executeEnrichmentFlow(token, flowId)
                                .then(data => setEnrichmentState({isLoading: false, data, error: '', running: []}))
                                .catch(err => setEnrichmentState(current => ({
                                    ...current,
                                    isLoading: false,
                                    error: onApiFailure ? onApiFailure(err, 'Falha ao atualizar.') : err.message,
                                })))
                        }}
                    >
                        Atualizar
                    </button>
                ) : null}
            </div>

            {!flowId ? (
                <p className="panel__feedback">Sem flow associado — enriquecimento não disponível.</p>
            ) : enrichmentState.error ? (
                <p className="form-message form-message--error">{enrichmentState.error}</p>
            ) : enrichmentState.isLoading && !enrichmentState.data ? (
                <p className="panel__feedback">Carregando enriquecimento...</p>
            ) : enrichmentState.data ? (
                <>
                    {enrichmentState.isLoading ? (
                        <p className="panel__feedback">Executando enriquecimento...</p>
                    ) : null}
                    {targets.length > 0 ? (
                        <div
                            className={`enrichment-targets${targets.length === 1 ? ' enrichment-targets--single' : ''}`}>
                            {targets.map(obs => (
                                <IpEnrichmentCard
                                    key={obs.target?.id}
                                    observation={obs}
                                    capabilities={capabilities}
                                    running={enrichmentState.running}
                                    onExecuteProvider={handleExecuteProvider}
                                />
                            ))}
                        </div>
                    ) : null}
                </>
            ) : null}
        </section>
    )
}

export default EnrichmentPanel
