function PasswordResetPage({
                               token,
                               password,
                               confirmPassword,
                               loading,
                               error,
                               successMessage,
                               onPasswordChange,
                               onNavigate,
                               onSubmit,
                           }) {
    function handleSubmit(event) {
        event.preventDefault()
        onSubmit()
    }

    return (
        <div className="standalone-shell">
            <section className="standalone-card standalone-card--narrow">
                <span className="auth-hero__eyebrow">Redefinição de senha</span>
                <h1>Defina uma nova senha.</h1>
                <p>
                    Use o token recebido na solicitação de recuperação para criar uma nova credencial de acesso
                    ao workspace do IBRExplorer.
                </p>

                <form className="auth-form" onSubmit={handleSubmit}>
                    <label className="field">
                        <span>Token de redefinição</span>
                        <textarea
                            value={token}
                            onChange={(event) => onPasswordChange('token', event.target.value)}
                            rows="4"
                            placeholder="Cole aqui o token recebido"
                        />
                    </label>

                    <label className="field">
                        <span>Nova senha</span>
                        <input
                            type="password"
                            value={password}
                            onChange={(event) => onPasswordChange('password', event.target.value)}
                            autoComplete="new-password"
                        />
                    </label>

                    <label className="field">
                        <span>Confirmação</span>
                        <input
                            type="password"
                            value={confirmPassword}
                            onChange={(event) => onPasswordChange('confirmPassword', event.target.value)}
                            autoComplete="new-password"
                        />
                    </label>

                    {error ? <p className="form-message form-message--error">{error}</p> : null}
                    {successMessage ? <p className="form-message form-message--success">{successMessage}</p> : null}

                    <button className="button button--primary" type="submit" disabled={loading}>
                        {loading ? 'Atualizando...' : 'Salvar nova senha'}
                    </button>
                </form>

                <div className="auth-card__footer">
                    <span>Lembrou sua senha?</span>
                    <button className="button button--ghost" onClick={() => onNavigate('/login')}>
                        Voltar para login
                    </button>
                </div>
            </section>
        </div>
    )
}

export default PasswordResetPage
