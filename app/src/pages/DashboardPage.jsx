import MetricCard from '../components/MetricCard'
import StatusBadge from '../components/StatusBadge'
import EmptyState from '../components/EmptyState'
import {formatDateTime, formatRelativePercent, getCaptureStatus, getFileLabel} from '../lib/formatters'

function DashboardPage({captures, capturesState, session, onNavigate, onRefresh}) {
    const queued = captures.filter((capture) => capture.status === 3 || capture.status === 4).length
    const processed = captures.filter((capture) => capture.status === 6).length
    const errors = captures.filter((capture) => capture.status === 5).length

    return (
        <div className="page-grid dashboard-page">
            <section className="hero-banner dashboard-hero">
                <div>
                    <span className="hero-banner__eyebrow">Visão geral</span>
                    <h2>Bem-vindo de volta, {session?.user?.name ?? 'analista'}.</h2>
                    <p>
                        Acompanhe o estado das capturas PCAP/PCAPNG, revise arquivos processados e acesse
                        detalhes de flows para investigar o tráfego observado.
                    </p>
                </div>

                <div className="hero-banner__actions">
                    <button className="button button--primary" onClick={() => onNavigate('/captures/upload')}>
                        Novo upload
                    </button>
                    <button className="button button--ghost" onClick={onRefresh}>
                        Atualizar lista
                    </button>
                </div>
            </section>

            <section className="metrics-grid">
                <MetricCard
                    label="Capturas"
                    value={capturesState.total ?? captures.length}
                    hint="Total conhecido pela API"
                    accent="cyan"
                />
                <MetricCard
                    label="Em andamento"
                    value={queued}
                    hint="Aguardando fila ou em parse"
                    accent="amber"
                />
                <MetricCard
                    label="Processadas"
                    value={processed}
                    hint="Pipeline concluido"
                    accent="green"
                />
                <MetricCard
                    label="Erros"
                    value={errors}
                    hint="Arquivos que exigem reprocessamento"
                    accent="red"
                />
            </section>

            <section className="panel panel--wide">
                <div className="panel__header">
                    <div>
                        <span className="panel__eyebrow">Atividade recente</span>
                        <h3>Últimas capturas</h3>
                    </div>
                    <button className="button button--ghost" onClick={() => onNavigate('/captures')}>
                        Ver todas
                    </button>
                </div>

                {capturesState.isLoading ? (
                    <p className="panel__feedback">Carregando capturas...</p>
                ) : captures.length === 0 ? (
                    <EmptyState
                        title="Nenhuma captura disponível"
                        copy="Assim que um arquivo for enviado pela tela de upload ele aparece aqui."
                        actionLabel="Abrir upload"
                        onAction={() => onNavigate('/captures/upload')}
                    />
                ) : (
                    <div className="capture-list">
                        {captures.slice(0, 4).map((capture) => {
                            const status = getCaptureStatus(capture.status)

                            return (
                                <button
                                    key={capture.id}
                                    className="capture-card"
                                    onClick={() => onNavigate(`/captures/${capture.id}`)}
                                >
                                    <div className="capture-card__topline">
                                        <strong>{getFileLabel(capture.file)}</strong>
                                        <StatusBadge status={status}/>
                                    </div>
                                    <span className="capture-card__meta">Chave {capture.key}</span>
                                    <div className="capture-card__stats">
                                        <span>Criado em {formatDateTime(capture.createdAt)}</span>
                                        <span>Processamento {formatRelativePercent(capture.processed)}</span>
                                    </div>
                                </button>
                            )
                        })}
                    </div>
                )}
            </section>

        </div>
    )
}

export default DashboardPage
