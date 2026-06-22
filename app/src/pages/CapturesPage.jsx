import {useDeferredValue, useEffect, useState} from 'react'
import EmptyState from '../components/EmptyState'
import StatusBadge from '../components/StatusBadge'
import {
    formatDateTime,
    formatRelativePercent,
    getCaptureStatus,
    getCaptureVisibility,
    getFileLabel,
} from '../lib/formatters'
import {listPcapFiles} from '../lib/api'

const PAGE_SIZE = 16

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

function CapturesPage({token, onNavigate, onApiFailure}) {
    const [state, setState] = useState({items: [], total: 0, isLoading: false, error: ''})
    const [page, setPage] = useState(1)
    const [refreshKey, setRefreshKey] = useState(0)
    const [search, setSearch] = useState('')
    const deferredSearch = useDeferredValue(search)
    const filteredCaptures = state.items.filter((capture) => matchesCapture(capture, deferredSearch))
    const totalPages = Math.max(1, Math.ceil((state.total ?? 0) / PAGE_SIZE))
    const emptyCopy = search
        ? 'Nenhum arquivo combina com a busca atual.'
        : 'Envie um arquivo PCAP ou PCAPNG para iniciar a análise.'

    useEffect(() => {
        if (!token) {
            return
        }

        let ignore = false

        async function load() {
            setState((current) => ({...current, isLoading: true, error: ''}))

            try {
                const response = await listPcapFiles(token, {page, limit: PAGE_SIZE})

                if (ignore) return

                setState({
                    items: response.entities ?? [],
                    total: response.total ?? 0,
                    isLoading: false,
                    error: '',
                })
            } catch (error) {
                if (ignore) return

                setState((current) => ({
                    ...current,
                    isLoading: false,
                    error: onApiFailure(error, 'Não foi possível carregar as capturas.'),
                }))
            }
        }

        void load()

        return () => {
            ignore = true
        }
    }, [token, page, refreshKey, onApiFailure])

    useEffect(() => {
        if (!state.isLoading && page > totalPages) {
            setPage(totalPages)
        }
    }, [page, totalPages, state.isLoading])

    return (
        <div className="page-grid captures-page">
            <section className="panel panel--wide">
                <div className="panel__header">
                    <div>
                        <span className="panel__eyebrow">Capturas</span>
                        <h2>Minhas capturas</h2>
                    </div>

                    <div className="panel__actions">
                        <input
                            className="search-input"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Buscar por chave, nome ou status"
                        />
                        <button className="button button--ghost" onClick={() => setRefreshKey((key) => key + 1)}>
                            Atualizar
                        </button>
                        <button className="button button--primary" onClick={() => onNavigate('/captures/upload')}>
                            Novo upload
                        </button>
                    </div>
                </div>

                {state.error ? <p className="form-message form-message--error">{state.error}</p> : null}

                {state.isLoading ? (
                    <p className="panel__feedback">Consultando API...</p>
                ) : state.items.length === 0 ? (
                    <EmptyState
                        title="Nenhuma captura encontrada"
                        copy={emptyCopy}
                        actionLabel="Abrir upload"
                        onAction={() => onNavigate('/captures/upload')}
                    />
                ) : (
                    <>
                        {filteredCaptures.length === 0 ? (
                            <p className="panel__feedback">Nenhum arquivo desta página combina com a busca atual.</p>
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

                        <div className="table-pagination">
                            <span className="table-muted">
                                {state.total} {state.total === 1 ? 'arquivo' : 'arquivos'} · Página {page} de {totalPages}
                            </span>
                            <div className="pagination-actions">
                                <button
                                    className="button button--ghost"
                                    disabled={page <= 1 || state.isLoading}
                                    onClick={() => setPage((current) => Math.max(1, current - 1))}
                                >
                                    Anterior
                                </button>
                                <button
                                    className="button button--ghost"
                                    disabled={page >= totalPages || state.isLoading}
                                    onClick={() => setPage((current) => Math.min(totalPages, current + 1))}
                                >
                                    Próxima
                                </button>
                            </div>
                        </div>
                    </>
                )}
            </section>
        </div>
    )
}

export default CapturesPage
