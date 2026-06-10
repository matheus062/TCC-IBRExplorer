import {useDeferredValue, useEffect, useState} from 'react'
import EmptyState from '../components/EmptyState'
import StatusBadge from '../components/StatusBadge'
import {formatBytes, formatDateTime, getCaptureStatus, getFileLabel,} from '../lib/formatters'
import {listPublicPcapFiles} from '../lib/api'

function matchesCapture(capture, term) {
    if (!term) {
        return true
    }

    const normalizedTerm = term.toLowerCase()
    const haystack = [
        capture.id,
        capture.key,
        getFileLabel(capture.file),
        capture.createdBy?.name,
    ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase()

    return haystack.includes(normalizedTerm)
}

function ExplorePage({token, onNavigate, onApiFailure}) {
    const [state, setState] = useState({items: [], total: 0, isLoading: false, error: ''})
    const [refreshKey, setRefreshKey] = useState(0)
    const [search, setSearch] = useState('')
    const deferredSearch = useDeferredValue(search)
    const filtered = state.items.filter((c) => matchesCapture(c, deferredSearch))
    const emptyCopy = search
        ? 'Nenhum arquivo combina com a busca atual.'
        : 'Nenhuma captura pública disponível no momento.'

    useEffect(() => {
        async function load() {
            setState((s) => ({...s, isLoading: true, error: ''}))

            try {
                const response = await listPublicPcapFiles(token)

                setState({
                    items: response.entities ?? [],
                    total: response.total ?? 0,
                    isLoading: false,
                    error: '',
                })
            } catch (error) {
                const msg = onApiFailure(error, 'Não foi possível carregar as capturas públicas.')

                setState((s) => ({...s, isLoading: false, error: msg}))
            }
        }

        void load()
    }, [token, refreshKey])

    return (
        <div className="page-grid captures-page">
            <section className="panel panel--wide">
                <div className="panel__header">
                    <div>
                        <span className="panel__eyebrow">Explorar</span>
                        <h2>Capturas PCAP/PCAPNG públicas</h2>
                    </div>

                    <div className="panel__actions">
                        <input
                            className="search-input"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Buscar por nome ou autor"
                        />
                        <button
                            className="button button--ghost"
                            onClick={() => setRefreshKey((k) => k + 1)}
                        >
                            Atualizar
                        </button>
                    </div>
                </div>

                {state.error ? <p className="form-message form-message--error">{state.error}</p> : null}

                {state.isLoading ? (
                    <p className="panel__feedback">Consultando API...</p>
                ) : filtered.length === 0 ? (
                    <EmptyState
                        title="Nenhuma captura pública encontrada"
                        copy={emptyCopy}
                    />
                ) : (
                    <div className="table-wrap">
                        <table className="data-table data-table--clickable captures-table">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Arquivo</th>
                                <th>Enviado por</th>
                                <th>Status</th>
                                <th>Tamanho</th>
                                <th>Criado em</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody>
                            {filtered.map((capture) => (
                                <tr key={capture.id} onClick={() => onNavigate(`/captures/${capture.id}`)}>
                                    <td>#{capture.id}</td>
                                    <td>
                                        <div className="table-primary-cell">
                                            <strong>{getFileLabel(capture.file)}</strong>
                                            <span className="table-muted">{capture.key}</span>
                                        </div>
                                    </td>
                                    <td className="table-soft">{capture.createdBy?.name ?? '—'}</td>
                                    <td>
                                        <StatusBadge status={getCaptureStatus(capture.status)}/>
                                    </td>
                                    <td className="table-soft">{formatBytes(capture.fileSize)}</td>
                                    <td className="table-soft">{formatDateTime(capture.createdAt)}</td>
                                    <td>
                                        <button
                                            className="button button--ghost"
                                            onClick={(e) => {
                                                e.stopPropagation()
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

export default ExplorePage
