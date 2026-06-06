import {useRef} from 'react'
import {getUserAvatarSrc, getUserEmail, getUserInitials} from '../lib/formatters'

function ProfilePage({
                         session,
                         passwordForm,
                         loading,
                         error,
                         successMessage,
                         onPasswordFieldChange,
                         onChangePassword,
                         onProfileImageChange,
                         profileImageLoading,
                         onLogout,
                     }) {
    const fileInputRef = useRef(null)

    function handleSubmit(event) {
        event.preventDefault()
        onChangePassword()
    }

    function handleAvatarClick() {
        fileInputRef.current?.click()
    }

    async function handleImageChange(event) {
        const file = event.target.files?.[0]
        event.target.value = ''

        if (!file) {
            return
        }

        const ext = file.name.split('.').pop()?.toLowerCase() ?? ''
        const data = await new Promise((resolve, reject) => {
            const reader = new FileReader()
            reader.onload = () => resolve(String(reader.result).split(',')[1] ?? '')
            reader.onerror = reject
            reader.readAsDataURL(file)
        })

        onProfileImageChange({
            data,
            ext,
            name: file.name,
        })
    }

    const user = session?.user ?? {}
    const userName = user.name ?? 'Usuário'
    const userEmail = getUserEmail(user)
    const avatarSrc = getUserAvatarSrc(user)

    return (
        <div className="profile-page">
            <section className="profile-identity-card">
                <div className="profile-identity-card__media">
                    <button
                        className="user-avatar user-avatar--large profile-avatar-button"
                        type="button"
                        onClick={handleAvatarClick}
                        disabled={profileImageLoading}
                        aria-label="Alterar foto de perfil"
                    >
                        {avatarSrc ? <img src={avatarSrc} alt=""/> : getUserInitials(userName)}
                    </button>
                    <input
                        ref={fileInputRef}
                        className="profile-avatar-input"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        onChange={handleImageChange}
                    />
                    <div className="profile-identity-card__title">
                        <strong>{userName}</strong>
                        <span>{userEmail}</span>
                        <small>{profileImageLoading ? 'Atualizando foto...' : 'Clique na foto para alterar'}</small>
                    </div>
                </div>

                <div className="profile-identity-card__details">
                    <div>
                        <span>ID do usuário</span>
                        <strong>{user.id ?? 'Não informado'}</strong>
                    </div>
                    <div>
                        <span>Status</span>
                        <strong>{user.entityStatus ? 'Ativo' : 'Não informado'}</strong>
                    </div>
                </div>

                <button className="button button--ghost" onClick={onLogout}>
                    Sair
                </button>
            </section>

            <section className="panel panel--wide">
                <div className="panel__header">
                    <div>
                        <span className="panel__eyebrow">Perfil</span>
                        <h2>Dados do usuário</h2>
                    </div>
                </div>

                <div className="profile-data-grid">
                    <label className="field">
                        <span>Nome</span>
                        <input type="text" value={userName} readOnly/>
                    </label>

                    <label className="field">
                        <span>Email</span>
                        <input type="email" value={userEmail} readOnly/>
                    </label>

                    <label className="field">
                        <span>Nível de Permissão</span>
                        <input type="text" value={session?.userRoleLabel ?? 'Sem nível definido'} readOnly/>
                    </label>
                </div>
            </section>

            <section className="panel panel--wide">
                <div className="panel__header">
                    <div>
                        <span className="panel__eyebrow">Segurança</span>
                        <h2>Troca de senha</h2>
                    </div>
                </div>

                <form className="auth-form" onSubmit={handleSubmit}>
                    <label className="field">
                        <span>Nova senha</span>
                        <input
                            type="password"
                            value={passwordForm.password}
                            onChange={(event) => onPasswordFieldChange('password', event.target.value)}
                            autoComplete="new-password"
                        />
                    </label>

                    <label className="field">
                        <span>Confirmação</span>
                        <input
                            type="password"
                            value={passwordForm.confirmPassword}
                            onChange={(event) => onPasswordFieldChange('confirmPassword', event.target.value)}
                            autoComplete="new-password"
                        />
                    </label>

                    {error ? <p className="form-message form-message--error">{error}</p> : null}
                    {successMessage ? <p className="form-message form-message--success">{successMessage}</p> : null}

                    <button className="button button--primary" type="submit" disabled={loading}>
                        {loading ? 'Atualizando...' : 'Salvar nova senha'}
                    </button>
                </form>
            </section>
        </div>
    )
}

export default ProfilePage
