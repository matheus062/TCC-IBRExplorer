function PasswordResetPage({
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
                    Escolha uma nova senha com no mínimo 8 caracteres e ao menos um caractere especial.
                </p>

                <form className="auth-form" onSubmit={handleSubmit}>
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
