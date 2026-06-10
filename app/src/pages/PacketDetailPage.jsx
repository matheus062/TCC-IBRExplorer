import {useEffect, useState} from 'react'
import EmptyState from '../components/EmptyState'
import EnrichmentPanel from '../components/EnrichmentPanel'
import {listPcapFlows} from '../lib/api'
import {formatBytes, formatDateTime, formatEndpoint, getFileLabel, getProtocolLabel,} from '../lib/formatters'

function formatTcpFlags(value) {
    if (value === undefined || value === null || value === '') return '—'
    return `0x${Number(value).toString(16).toUpperCase()}`
}

function PacketDetailPage({
                              token,
                              packet,
                              capture,
                              flow,
                              isLoading,
                              error,
                              captureId,
                              flowId,
                              onNavigate,
                              onApiFailure,
                          }) {
    const [resolvedFlowId, setResolvedFlowId] = useState(flowId ?? null)
    const [activePanel, setActivePanel] = useState(null)

    useEffect(() => {
        if (flowId) {
            setResolvedFlowId(flowId)
            return
        }

        if (!packet?.flowKey || !packet?.pcap?.id) {
            setResolvedFlowId(null)
            return
        }

        let ignore = false

        listPcapFlows(token, {
            pcapId: packet.pcap.id,
            limit: 1,
            extraQuery: {flowKey: packet.flowKey},
        })
            .then((response) => {
                if (ignore) return
                const found = response.entities?.[0]
                setResolvedFlowId(found?.id ?? null)
            })
            .catch(() => {
                if (!ignore) setResolvedFlowId(null)
            })

        return () => {
            ignore = true
        }
    }, [flowId, packet?.flowKey, packet?.pcap?.id, token])

    if (isLoading) {
        return (
            <div className="page-grid">
                <section className="panel panel--wide">
                    <p className="panel__feedback">Carregando pacote...</p>
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

    if (!packet) {
        return (
            <div className="page-grid">
                <section className="panel panel--wide">
                    <EmptyState
                        title="Pacote não localizado"
                        copy="Volte para a lista e selecione outro registro."
                        actionLabel="Voltar"
                        onAction={() => onNavigate(flowId
                            ? `/captures/${captureId}/flows/${flowId}`
                            : `/captures/${captureId}/packets`
                        )}
                    />
                </section>
            </div>
        )
    }

    const captureLabel = capture ? getFileLabel(capture.file) : `Captura #${captureId}`

    function renderBreadcrumb() {
        if (flowId && flow) {
            return (
                <button
                    className="breadcrumb-back"
                    onClick={() => onNavigate(`/captures/${captureId}/flows/${flowId}`)}
                >
                    ← {captureLabel} / Flows
                </button>
            )
        }

        if (flowId) {
            return (
                <button
                    className="breadcrumb-back"
                    onClick={() => onNavigate(`/captures/${captureId}/flows/${flowId}`)}
                >
                    ← {captureLabel} / Flows
                </button>
            )
        }

        return (
            <button
                className="breadcrumb-back"
                onClick={() => onNavigate(`/captures/${captureId}/packets`)}
            >
                ← {captureLabel} / Pacotes
            </button>
        )
    }

    return (
        <div className="page-grid">
            <section className="capture-header">
                {renderBreadcrumb()}
                <h2 className="flow-header__route">
                    <span>Pacote</span>
                    <span className="flow-header__arrow">#</span>
                    <span>{packet.packetNumber}</span>
                </h2>
            </section>

            <div className="flow-summary">
                {packet.protocol != null ? (
                    <span className="status-badge status-badge--muted">{getProtocolLabel(packet.protocol)}</span>
                ) : null}
                <div className="flow-summary__stats">
                    <div className="flow-summary__stat">
                        <span className="flow-summary__stat-label">Timestamp</span>
                        <span className="flow-summary__stat-value">{formatDateTime(packet.timestamp)}</span>
                    </div>
                    <div className="flow-summary__stat">
                        <span className="flow-summary__stat-label">Tamanho capturado</span>
                        <span className="flow-summary__stat-value">{formatBytes(packet.capturedLen)}</span>
                    </div>
                    <div className="flow-summary__stat">
                        <span className="flow-summary__stat-label">Tamanho original</span>
                        <span className="flow-summary__stat-value">{formatBytes(packet.originalLen)}</span>
                    </div>
                    {packet.ttl != null ? (
                        <div className="flow-summary__stat">
                            <span className="flow-summary__stat-label">TTL</span>
                            <span className="flow-summary__stat-value">{packet.ttl}</span>
                        </div>
                    ) : null}
                </div>
            </div>

            <div className={`flow-detail-grid${activePanel ? ` flow-detail-grid--${activePanel}` : ''}`}>
                <div onMouseEnter={() => setActivePanel('left')}>
                    <section className="panel" style={{marginBottom: '1rem'}}>
                        <div className="panel__header">
                            <div>
                                <span className="panel__eyebrow">Rede</span>
                                <h3>Endereços e portas</h3>
                            </div>
                        </div>
                        <div className="detail-grid">
                            <div className="detail-card">
                                <span>Origem</span>
                                <strong>{formatEndpoint(packet.srcIp, packet.srcPort)}</strong>
                            </div>
                            <div className="detail-card">
                                <span>Destino</span>
                                <strong>{formatEndpoint(packet.dstIp, packet.dstPort)}</strong>
                            </div>
                            {packet.ipVersion != null ? (
                                <div className="detail-card">
                                    <span>Versão IP</span>
                                    <strong>IPv{packet.ipVersion}</strong>
                                </div>
                            ) : null}
                            {packet.ipLength != null ? (
                                <div className="detail-card">
                                    <span>IP Length</span>
                                    <strong>{packet.ipLength} bytes</strong>
                                </div>
                            ) : null}
                            {packet.payloadSize != null ? (
                                <div className="detail-card">
                                    <span>Payload</span>
                                    <strong>{packet.payloadSize} bytes</strong>
                                </div>
                            ) : null}
                        </div>
                    </section>

                    <section className="panel" style={{marginBottom: '1rem'}}>
                        <div className="panel__header">
                            <div>
                                <span className="panel__eyebrow">Camada de transporte</span>
                                <h3>Flags e ICMP</h3>
                            </div>
                        </div>
                        <div className="detail-grid">
                            {packet.tcpFlags != null ? (
                                <div className="detail-card">
                                    <span>TCP Flags</span>
                                    <strong>{formatTcpFlags(packet.tcpFlags)}</strong>
                                </div>
                            ) : null}
                            {packet.icmpType != null ? (
                                <div className="detail-card">
                                    <span>ICMP Type</span>
                                    <strong>{packet.icmpType}</strong>
                                </div>
                            ) : null}
                            {packet.icmpCode != null ? (
                                <div className="detail-card">
                                    <span>ICMP Code</span>
                                    <strong>{packet.icmpCode}</strong>
                                </div>
                            ) : null}
                            {packet.tcpFlags == null && packet.icmpType == null ? (
                                <div className="detail-card">
                                    <span>Flags/ICMP</span>
                                    <strong>N/A</strong>
                                </div>
                            ) : null}
                        </div>
                    </section>

                    <section className="panel">
                        <div className="panel__header">
                            <div>
                                <span className="panel__eyebrow">Metadados</span>
                                <h3>Identificação do pacote</h3>
                            </div>
                        </div>
                        <div className="detail-grid">
                            <div className="detail-card">
                                <span>Número</span>
                                <strong>#{packet.packetNumber}</strong>
                            </div>
                            <div className="detail-card">
                                <span>Offset</span>
                                <strong>{packet.offset}</strong>
                            </div>
                            <div className="detail-card">
                                <span>Flow Key</span>
                                <strong style={{fontFamily: 'monospace', fontSize: '0.8em', wordBreak: 'break-all'}}>
                                    {packet.flowKey ?? '—'}
                                </strong>
                            </div>
                            <div className="detail-card">
                                <span>PCAP</span>
                                <strong>#{packet.pcap?.id}</strong>
                            </div>
                        </div>
                    </section>
                </div>

                <div onMouseEnter={() => setActivePanel('right')}>
                    <EnrichmentPanel
                        token={token}
                        flowId={resolvedFlowId}
                        onApiFailure={onApiFailure}
                    />
                </div>
            </div>
        </div>
    )
}

export default PacketDetailPage
