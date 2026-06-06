import {useDeferredValue, useState} from 'react'
import EmptyState from '../components/EmptyState'
import StatusBadge from '../components/StatusBadge'
import {
    formatDateTime,
    formatRelativePercent,
    getCaptureStatus,
    getCaptureVisibility,
    getFileLabel,
} from '../lib/formatters'

function matchesCapture(capture, term) {
    if (!term) {
        return true
    }

    const normalizedTerm = term.toLowerCase()
    const haystack = [
        capture.id,
        capture.key,
        getFileLabel(capture.file),
        getCaptureStatus(capture.status).label,
        getCaptureVisibility(capture.visibility).label,
    ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase()

    return haystack.includes(normalizedTerm)
}

function CapturesPage({captures, capturesState, onNavigate, onRefresh}) {
    const [search, setSearch] = useState('')
    const deferredSearch = useDeferredValue(search)
    const filteredCaptures = captures.filter((capture) => matchesCapture(capture, deferredSearch))
    const emptyCopy = search
        ? 'Nenhum arquivo combina com a busca atual.'
        : 'Envie um arquivo PCAP ou PCAPNG para iniciar a análise.'

    return (
        <div className="page-grid captures-page">
            <section className="panel panel--wide">
                <div className="panel__header">
                    <div>
                        <span className="panel__eyebrow">Capturas</span>
                        <h2>Inventário de capturas PCAP/PCAPNG</h2>
                    </div>

                    <div className="panel__actions">
                        <input
                            className="search-input"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Buscar por chave, nome ou status"
                        />
                        <button className="button button--ghost" onClick={onRefresh}>
                            Atualizar
                        </button>
                        <button className="button button--primary" onClick={() => onNavigate('/captures/upload')}>
                            Novo upload
                        </button>
                    </div>
                </div>

                {capturesState.error ? <p className="form-message form-message--error">{capturesState.error}</p> : null}

                {capturesState.isLoading ? (
                    <p className="panel__feedback">Consultando API...</p>
                ) : filteredCaptures.length === 0 ? (
                    <EmptyState
                        title="Nenhuma captura encontrada"
                        copy={emptyCopy}
                        actionLabel="Abrir upload"
                        onAction={() => onNavigate('/captures/upload')}
                    />
                ) : (
                    <div className="table-wrap">
                        <table className="data-table data-table--clickable captures-table">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Arquivo</th>
                                <th>Status</th>
                                <th>Visibilidade</th>
                                <th>Progresso</th>
                                <th>Criado em</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody>
                            {filteredCaptures.map((capture) => (
                                <tr key={capture.id} onClick={() => onNavigate(`/captures/${capture.id}`)}>
                                    <td>#{capture.id}</td>
                                    <td>
                                        <div className="table-primary-cell">
                                            <strong>{getFileLabel(capture.file)}</strong>
                                            <span className="table-muted">{capture.key}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <StatusBadge status={getCaptureStatus(capture.status)}/>
                                    </td>
                                    <td className="table-soft">{getCaptureVisibility(capture.visibility).label}</td>
                                    <td className="table-strong">{formatRelativePercent(capture.processed)}</td>
                                    <td className="table-soft">{formatDateTime(capture.createdAt)}</td>
                                    <td>
                                        <button
                                            className="button button--ghost"
                                            onClick={(event) => {
                                                event.stopPropagation()
                                                onNavigate(`/captures/${capture.id}`)
                                            }}
                                        >
                                            Abrir
                                        </button>
                                    </td>
                                </tr>
                            ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </section>
        </div>
    )
}

export default CapturesPage
