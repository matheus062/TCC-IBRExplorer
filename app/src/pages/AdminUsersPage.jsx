import {useCallback, useEffect, useRef, useState} from 'react'
import EmptyState from '../components/EmptyState'
import {createUser, listUsers, readUser, updateUser} from '../lib/api'
import {formatDateTime, getUserAvatarSrc, getUserEmail, getUserInitials, getUserRoleLabel} from '../lib/formatters'

const ROLE_OPTIONS = [
    {value: 2, label: 'Administrador'},
    {value: 3, label: 'Suporte'},
    {value: 4, label: 'Usuário'},
]

const EMPTY_FORM = {name: '', email: '', role: 4}

function getStatusLabel(status) {
    return status === 1 || status === '1' ? 'Ativo' : 'Inativo'
}

function AdminUsersPage({token, onApiFailure}) {
    const [state, setState] = useState({items: [], total: 0, isLoading: true, error: ''})
    const [avatarCache, setAvatarCache] = useState({})
    const fetchedRef = useRef(new Set())

    const [modal, setModal] = useState(null) // null | 'create' | 'edit'
    const [editUserId, setEditUserId] = useState(null)
    const [form, setForm] = useState(EMPTY_FORM)
    const [formLoading, setFormLoading] = useState(false)
    const [formError, setFormError] = useState('')

    const loadUsers = useCallback(async () => {
        setState((current) => ({...current, isLoading: true, error: ''}))

        try {
            const response = await listUsers(token)
            const items = response.entities ?? []

            setState({
                items,
                total: response.total ?? 0,
                isLoading: false,
                error: '',
            })

            for (const user of items) {
                if (fetchedRef.current.has(user.id)) {
                    continue
                }

                fetchedRef.current.add(user.id)

                readUser(token, user.id).then((full) => {
                    setAvatarCache((c) => ({...c, [user.id]: getUserAvatarSrc(full)}))
                }).catch(() => {
                    setAvatarCache((c) => ({...c, [user.id]: ''}))
                })
            }
        } catch (error) {
            setState({
                items: [],
                total: 0,
                isLoading: false,
                error: onApiFailure(error, 'Não foi possível carregar os usuários.'),
            })
        }
    }, [onApiFailure, token])

    useEffect(() => {
        if (!token) {
            return
        }

        void loadUsers()
    }, [loadUsers, token])

    function openCreate() {
        setForm(EMPTY_FORM)
        setEditUserId(null)
        setFormError('')
        setModal('create')
    }

    function openEdit(user) {
        setForm({
            name: user.name ?? '',
            email: getUserEmail(user),
            role: user.roles?.[0]?.type ?? 4,
        })
        setEditUserId(user.id)
        setFormError('')
        setModal('edit')
    }

    function closeModal() {
        setModal(null)
        setFormError('')
    }

    async function handleSave() {
        if (!form.name.trim() || !form.email.trim()) {
            setFormError('Nome e email são obrigatórios.')
            return
        }

        setFormLoading(true)
        setFormError('')

        const payload = {
            name: form.name.trim(),
            email: form.email.trim(),
            roles: [{type: Number(form.role)}],
        }

        try {
            if (modal === 'create') {
                await createUser(token, payload)
            } else {
                await updateUser(token, editUserId, payload)
                fetchedRef.current.delete(editUserId)
            }

            closeModal()
            await loadUsers()
        } catch (error) {
            setFormError(onApiFailure(error, 'Não foi possível salvar o usuário.'))
        } finally {
            setFormLoading(false)
        }
    }

    return (
        <div className="page-grid">
            <section className="hero-banner">
                <div>
                    <span className="hero-banner__eyebrow">Administração</span>
                    <h2>Usuários</h2>
                    <p>Consulte, crie e edite os usuários cadastrados e seus níveis de permissão.</p>
                </div>

                <div className="hero-banner__actions">
                    <button className="button button--ghost" onClick={loadUsers}>Atualizar</button>
                    <button className="button button--primary" onClick={openCreate}>Novo usuário</button>
                </div>
            </section>

            <section className="metrics-grid">
                <div className="metric-card metric-card--cyan">
                    <span className="metric-card__label">Usuários</span>
                    <strong className="metric-card__value">{state.total}</strong>
                    <span className="metric-card__hint">Total retornado pela API</span>
                </div>
            </section>

            <section className="panel panel--wide">
                <div className="panel__header">
                    <div>
                        <span className="panel__eyebrow">Controle de acesso</span>
                        <h3>Listagem de usuários</h3>
                    </div>
                </div>

                {state.isLoading ? (
                    <p className="panel__feedback">Carregando usuários...</p>
                ) : state.error ? (
                    <p className="form-message form-message--error">{state.error}</p>
                ) : state.items.length === 0 ? (
                    <EmptyState title="Nenhum usuário encontrado" copy="A API não retornou usuários cadastrados."/>
                ) : (
                    <div className="table-wrap">
                        <table className="data-table">
                            <thead>
                            <tr>
                                <th style={{width: '2.5rem'}}></th>
                                <th>Usuário</th>
                                <th>Nível de Permissão</th>
                                <th>Status</th>
                                <th>Criado em</th>
                                <th style={{width: '5rem'}}></th>
                            </tr>
                            </thead>
                            <tbody>
                            {state.items.map((user) => {
                                const avatarSrc = avatarCache[user.id]

                                return (
                                    <tr key={user.id}>
                                        <td style={{paddingRight: 0}}>
                                            <span className="user-avatar user-avatar--compact" aria-hidden="true">
                                                {avatarSrc
                                                    ? <img src={avatarSrc} alt=""/>
                                                    : getUserInitials(user.name ?? 'Usuário')}
                                            </span>
                                        </td>
                                        <td>
                                            <div className="table-primary-cell">
                                                <strong>{user.name ?? 'Usuário sem nome'}</strong>
                                                <span className="table-muted">{getUserEmail(user)}</span>
                                            </div>
                                        </td>
                                        <td>
                                            <span className="status-badge status-badge--info">
                                                {getUserRoleLabel(user.roles)}
                                            </span>
                                        </td>
                                        <td className="table-soft">{getStatusLabel(user.entityStatus)}</td>
                                        <td className="table-soft">{formatDateTime(user.createdAt)}</td>
                                        <td>
                                            <button className="button button--ghost" onClick={() => openEdit(user)}>
                                                Editar
                                            </button>
                                        </td>
                                    </tr>
                                )
                            })}
                            </tbody>
                        </table>
                    </div>
                )}
            </section>

            {modal ? (
                <div className="filter-modal">
                    <button className="filter-modal__backdrop" onClick={closeModal} aria-label="Fechar"/>
                    <div className="filter-modal__panel" style={{maxWidth: 480}}>
                        <div className="filter-modal__header">
                            <div>
                                <span className="panel__eyebrow">Usuários</span>
                                <h3>{modal === 'create' ? 'Novo usuário' : 'Editar usuário'}</h3>
                            </div>
                            <button className="button button--ghost" onClick={closeModal} disabled={formLoading}>
                                ×
                            </button>
                        </div>

                        <label className="field">
                            <span>Nome</span>
                            <input
                                type="text"
                                value={form.name}
                                onChange={(e) => setForm((f) => ({...f, name: e.target.value}))}
                                autoComplete="off"
                                disabled={formLoading}
                            />
                        </label>

                        <label className="field">
                            <span>Email</span>
                            <input
                                type="email"
                                value={form.email}
                                onChange={(e) => setForm((f) => ({...f, email: e.target.value}))}
                                autoComplete="off"
                                disabled={formLoading}
                            />
                        </label>

                        <label className="field">
                            <span>Nível de permissão</span>
                            <select
                                value={form.role}
                                onChange={(e) => setForm((f) => ({...f, role: Number(e.target.value)}))}
                                disabled={formLoading}
                            >
                                {ROLE_OPTIONS.map((opt) => (
                                    <option key={opt.value} value={opt.value}>{opt.label}</option>
                                ))}
                            </select>
                        </label>

                        {formError ? <p className="form-message form-message--error">{formError}</p> : null}

                        <div className="filter-modal__actions">
                            <button className="button button--ghost" onClick={closeModal} disabled={formLoading}>
                                Cancelar
                            </button>
                            <button className="button button--primary" onClick={handleSave} disabled={formLoading}>
                                {formLoading ? 'Salvando...' : 'Salvar'}
                            </button>
                        </div>
                    </div>
                </div>
            ) : null}
        </div>
    )
}

export default AdminUsersPage
