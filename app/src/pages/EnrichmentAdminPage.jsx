import {useCallback, useEffect, useMemo, useState} from 'react'
import EmptyState from '../components/EmptyState'
import {listEnrichmentIntegrations, updateEnrichmentIntegration} from '../lib/api'

function normalizeArray(value) {
    if (Array.isArray(value)) {
        return value
    }

    if (typeof value !== 'string' || value.trim() === '') {
        return []
    }

    try {
        const parsed = JSON.parse(value)
        return Array.isArray(parsed) ? parsed : []
    } catch {
        return []
    }
}

function normalizeObject(value) {
    if (value && typeof value === 'object' && !Array.isArray(value)) {
        return value
    }

    if (typeof value !== 'string' || value.trim() === '') {
        return {}
    }

    try {
        const parsed = JSON.parse(value)
        return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {}
    } catch {
        return {}
    }
}

function formatLimit(used, limit) {
    if (limit === null || limit === undefined || limit === '') {
        return `${used ?? 0} / sem limite configurado`
    }

    return `${used ?? 0} / ${limit}`
}

function EnrichmentAdminPage({token, onApiFailure}) {
    const [state, setState] = useState({
        items: [],
        isLoading: true,
        error: '',
    })
    const [drafts, setDrafts] = useState({})
    const [savingId, setSavingId] = useState(null)
    const [message, setMessage] = useState('')

    const loadIntegrations = useCallback(async () => {
        setState((current) => ({...current, isLoading: true, error: ''}))

        try {
            const response = await listEnrichmentIntegrations(token)
            const items = response.entities ?? []

            setState({
                items,
                isLoading: false,
                error: '',
            })
            setDrafts(Object.fromEntries(items.map((item) => [
                item.id,
                {
                    enabled: Boolean(item.enabled),
                    alwaysExecute: Boolean(item.alwaysExecute),
                    config: normalizeObject(item.config),
                    dailyLimit: item.dailyLimit ?? '',
                    weeklyLimit: item.weeklyLimit ?? '',
                    monthlyLimit: item.monthlyLimit ?? '',
                },
            ])))
        } catch (error) {
            setState({
                items: [],
                isLoading: false,
                error: onApiFailure(error, 'Não foi possível carregar as integrações de enriquecimento.'),
            })
        }
    }, [onApiFailure, token])

    useEffect(() => {
        if (!token) {
            return
        }

        void loadIntegrations()
    }, [loadIntegrations, token])

    const totalEnabled = useMemo(
        () => state.items.filter((item) => drafts[item.id]?.enabled).length,
        [drafts, state.items],
    )

    function updateDraft(id, updater) {
        setDrafts((current) => ({
            ...current,
            [id]: updater(current[id] ?? {enabled: false, config: {}}),
        }))
    }

    function updateConfigValue(id, fieldName, value) {
        updateDraft(id, (draft) => ({
            ...draft,
            config: {
                ...draft.config,
                [fieldName]: value,
            },
        }))
    }

    async function saveIntegration(integration) {
        const draft = drafts[integration.id]

        if (!draft) {
            return
        }

        setSavingId(integration.id)
        setMessage('')

        const toNullableInt = (value) => {
            if (value === '' || value === null || value === undefined) {
                return null
            }

            return Number(value)
        }

        try {
            await updateEnrichmentIntegration(token, integration.id, {
                enabled: draft.enabled,
                alwaysExecute: draft.alwaysExecute,
                config: draft.config,
                dailyLimit: toNullableInt(draft.dailyLimit),
                weeklyLimit: toNullableInt(draft.weeklyLimit),
                monthlyLimit: toNullableInt(draft.monthlyLimit),
            })
            setMessage(`${integration.name} atualizado.`)
            await loadIntegrations()
        } catch (error) {
            setMessage(onApiFailure(error, `Não foi possível atualizar ${integration.name}.`))
        } finally {
            setSavingId(null)
        }
    }

    return (
        <div className="page-grid">
            <section className="hero-banner">
                <div>
                    <span className="hero-banner__eyebrow">Administração</span>
                    <h2>Configurações de Enriquecimento</h2>
                    <p>
                        Configure os provedores que serão utilizados para agregar contexto aos alvos observados nas
                        capturas, como IPs de origem e destino compartilhados por flows e pacotes.
                    </p>
                </div>

                <div className="hero-banner__actions">
                    <button className="button button--ghost" onClick={loadIntegrations}>
                        Atualizar
                    </button>
                </div>
            </section>

            <section className="metrics-grid">
                <div className="metric-card metric-card--cyan">
                    <span className="metric-card__label">Integrações cadastradas</span>
                    <strong className="metric-card__value">{state.items.length}</strong>
                    <span className="metric-card__hint">Definidas na estrutura do banco</span>
                </div>
                <div className="metric-card metric-card--green">
                    <span className="metric-card__label">Ativas</span>
                    <strong className="metric-card__value">{totalEnabled}</strong>
                    <span className="metric-card__hint">Habilitadas para uso futuro</span>
                </div>
            </section>

            {message ? <p className="form-message form-message--success">{message}</p> : null}

            <section className="panel panel--wide">
                <div className="panel__header">
                    <div>
                        <span className="panel__eyebrow">Provedores</span>
                        <h3>Configurações e limites de uso</h3>
                    </div>
                </div>

                {state.isLoading ? (
                    <p className="panel__feedback">Carregando integrações...</p>
                ) : state.error ? (
                    <p className="form-message form-message--error">{state.error}</p>
                ) : state.items.length === 0 ? (
                    <EmptyState title="Nenhuma integração cadastrada"
                                copy="Execute as atualizações do banco para criar os provedores."/>
                ) : (
                    <div className="integration-grid">
                        {state.items.map((integration) => {
                            const schema = normalizeArray(integration.configSchema)
                            const draft = drafts[integration.id] ?? {enabled: false, config: {}}

                            return (
                                <article className="integration-card" key={integration.id}>
                                    <div className="integration-card__header">
                                        <div>
                                            <span className="panel__eyebrow">{integration.identifier}</span>
                                            <h4>{integration.name}</h4>
                                        </div>
                                    </div>

                                    <div className="integration-card__toggles">
                                        <label className="switch-field">
                                            <input
                                                type="checkbox"
                                                checked={Boolean(draft.enabled)}
                                                onChange={(event) => updateDraft(integration.id, (current) => ({
                                                    ...current,
                                                    enabled: event.target.checked,
                                                }))}
                                            />
                                            <span>{draft.enabled ? 'Ativa' : 'Inativa'}</span>
                                        </label>
                                        <label className="switch-field">
                                            <input
                                                type="checkbox"
                                                checked={Boolean(draft.alwaysExecute)}
                                                onChange={(event) => updateDraft(integration.id, (current) => ({
                                                    ...current,
                                                    alwaysExecute: event.target.checked,
                                                }))}
                                            />
                                            <span>Automática</span>
                                        </label>
                                    </div>

                                    <p className="integration-card__description">{integration.description}</p>

                                    <div className="usage-grid">
                                        <span><strong>Dia</strong>{formatLimit(integration.dailyUsed, integration.dailyLimit)}</span>
                                        <span><strong>Semana</strong>{formatLimit(integration.weeklyUsed, integration.weeklyLimit)}</span>
                                        <span><strong>Mês</strong>{formatLimit(integration.monthlyUsed, integration.monthlyLimit)}</span>
                                    </div>

                                    <div className="integration-card__section">
                                        <span
                                            className="integration-card__section-title">Credenciais e parâmetros</span>
                                        <div className="config-fields">
                                            {schema.map((field) => (
                                                <label className="field" key={field.name}>
                                                    <span>{field.label ?? field.name}</span>
                                                    <input
                                                        type={field.type === 'password' ? 'password' : field.type === 'number' ? 'number' : 'text'}
                                                        value={draft.config?.[field.name] ?? ''}
                                                        placeholder={field.default !== undefined ? String(field.default) : ''}
                                                        onChange={(event) => updateConfigValue(integration.id, field.name, event.target.value)}
                                                    />
                                                    {field.help ? <small>{field.help}</small> : null}
                                                </label>
                                            ))}
                                        </div>
                                    </div>

                                    <div className="integration-card__section">
                                        <span className="integration-card__section-title">Limites de uso</span>
                                        <div className="config-fields config-fields--limits">
                                            <label className="field">
                                                <span>Limite diário</span>
                                                <input
                                                    type="number"
                                                    value={draft.dailyLimit}
                                                    onChange={(event) => updateDraft(integration.id, (current) => ({
                                                        ...current,
                                                        dailyLimit: event.target.value,
                                                    }))}
                                                />
                                            </label>
                                            <label className="field">
                                                <span>Limite semanal</span>
                                                <input
                                                    type="number"
                                                    value={draft.weeklyLimit}
                                                    onChange={(event) => updateDraft(integration.id, (current) => ({
                                                        ...current,
                                                        weeklyLimit: event.target.value,
                                                    }))}
                                                />
                                            </label>
                                            <label className="field">
                                                <span>Limite mensal</span>
                                                <input
                                                    type="number"
                                                    value={draft.monthlyLimit}
                                                    onChange={(event) => updateDraft(integration.id, (current) => ({
                                                        ...current,
                                                        monthlyLimit: event.target.value,
                                                    }))}
                                                />
                                            </label>
                                        </div>
                                    </div>

                                    {integration.lastError ? (
                                        <p className="form-message form-message--error">{integration.lastError}</p>
                                    ) : null}

                                    <div className="integration-card__footer">
                                        <button
                                            className="button button--primary"
                                            onClick={() => saveIntegration(integration)}
                                            disabled={savingId === integration.id}
                                        >
                                            {savingId === integration.id ? 'Salvando...' : 'Salvar configuração'}
                                        </button>
                                    </div>
                                </article>
                            )
                        })}
                    </div>
                )}
            </section>
        </div>
    )
}

export default EnrichmentAdminPage
