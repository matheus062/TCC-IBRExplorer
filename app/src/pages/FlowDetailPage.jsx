import {useState} from 'react'
import EmptyState from '../components/EmptyState'
import EnrichmentPanel from '../components/EnrichmentPanel'
import PacketListPanel from '../components/PacketListPanel'
import {formatBytes, formatDateTime, formatEndpoint, getFileLabel, getProtocolLabel} from '../lib/formatters'

function formatFlowDuration(startTimestamp, endTimestamp) {
    if (!startTimestamp || !endTimestamp) return '—'

    const ms = new Date(endTimestamp) - new Date(startTimestamp)

    if (ms < 0) return '—'
    if (ms < 1000) return `${ms}ms`
    if (ms < 60000) return `${(ms / 1000).toFixed(1)}s`

    return `${(ms / 60000).toFixed(1)}min`
}

function FlowDetailPage({
                            token,
                            flow,
                            capture,
                            isLoading,
                            error,
                            captureId,
                            onNavigate,
                            onApiFailure,
                        }) {
    if (isLoading) {
        return (
            <div className="page-grid">
                <section className="panel panel--wide">
                    <p className="panel__feedback">Carregando flow...</p>
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

    if (!flow) {
        return (
            <div className="page-grid">
                <section className="panel panel--wide">
                    <EmptyState
                        title="Flow não localizado"
                        copy="Volte para a lista de flows e selecione outro registro."
                        actionLabel="Voltar para flows"
                        onAction={() => onNavigate(`/captures/${captureId}/flows`)}
                    />
                </section>
            </div>
        )
    }

    const [activePanel, setActivePanel] = useState(null)

    const captureLabel = capture ? getFileLabel(capture.file) : `Captura #${captureId}`
    const duration = formatFlowDuration(flow.startTimestamp, flow.endTimestamp)
    const pcapId = flow?.pcap?.id ?? null

    return (
        <div className="page-grid">
            <section className="capture-header">
                <button
                    className="breadcrumb-back"
                    onClick={() => onNavigate(`/captures/${captureId}/flows`)}
                >
                    ← {captureLabel} / Flows
                </button>
                <h2 className="flow-header__route">
                    <span>{formatEndpoint(flow.srcIp, flow.srcPort)}</span>
                    <span className="flow-header__arrow">→</span>
                    <span>{formatEndpoint(flow.dstIp, flow.dstPort)}</span>
                </h2>
            </section>

            <div className="flow-summary">
                <span className="status-badge status-badge--muted">{getProtocolLabel(flow.protocol)}</span>
                <div className="flow-summary__stats">
                    <div className="flow-summary__stat">
                        <span className="flow-summary__stat-label">Pacotes</span>
                        <span className="flow-summary__stat-value">{flow.packetCount}</span>
                    </div>
                    <div className="flow-summary__stat">
                        <span className="flow-summary__stat-label">Bytes</span>
                        <span className="flow-summary__stat-value">{formatBytes(flow.bytesTotal)}</span>
                    </div>
                    <div className="flow-summary__stat">
                        <span className="flow-summary__stat-label">Duração</span>
                        <span className="flow-summary__stat-value">{duration}</span>
                    </div>
                    <div className="flow-summary__stat">
                        <span className="flow-summary__stat-label">Início</span>
                        <span className="flow-summary__stat-value">{formatDateTime(flow.startTimestamp)}</span>
                    </div>
                    <div className="flow-summary__stat">
                        <span className="flow-summary__stat-label">Fim</span>
                        <span className="flow-summary__stat-value">{formatDateTime(flow.endTimestamp)}</span>
                    </div>
                    {(flow.protocol === 1 || flow.protocol === 58) && flow.icmpType != null ? (
                        <div className="flow-summary__stat">
                            <span className="flow-summary__stat-label">ICMP Type</span>
                            <span className="flow-summary__stat-value">{flow.icmpType}</span>
                        </div>
                    ) : null}
                    {(flow.protocol === 1 || flow.protocol === 58) && flow.icmpCode != null ? (
                        <div className="flow-summary__stat">
                            <span className="flow-summary__stat-label">ICMP Code</span>
                            <span className="flow-summary__stat-value">{flow.icmpCode}</span>
                        </div>
                    ) : null}
                </div>
            </div>

            <div className={`flow-detail-grid${activePanel ? ` flow-detail-grid--${activePanel}` : ''}`}>
                <div onMouseEnter={() => setActivePanel('left')}>
                    <PacketListPanel
                        token={token}
                        pcapId={pcapId}
                        flowKey={flow.flowKey}
                        cacheKey={`flow:${flow.id}`}
                        onPacketClick={(packet) => onNavigate(`/captures/${captureId}/flows/${flow.id}/packets/${packet.id}`)}
                        onApiFailure={onApiFailure}
                    />
                </div>

                <div onMouseEnter={() => setActivePanel('right')}>
                    <EnrichmentPanel
                        token={token}
                        flowId={flow.id}
                        onApiFailure={onApiFailure}
                    />
                </div>
            </div>
        </div>
    )
}

export default FlowDetailPage
