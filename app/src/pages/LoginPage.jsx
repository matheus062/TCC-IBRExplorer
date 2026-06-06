function LoginPage({
                       authMode,
                       credentials,
                       forgotEmail,
                       loading,
                       authError,
                       forgotMessage,
                       onAuthModeChange,
                       onCredentialChange,
                       onForgotEmailChange,
                       onLogin,
                       onForgotPassword,
                       onNavigate,
                   }) {
    function handleLoginSubmit(event) {
        event.preventDefault()
        onLogin()
    }

    function handleForgotSubmit(event) {
        event.preventDefault()
        onForgotPassword()
    }

    return (
        <div className="auth-shell">
            <section className="auth-hero">
                <div className="auth-hero__panel">
                    <span className="auth-hero__eyebrow">IBRExplorer</span>
                    <h1>Análise colaborativa de capturas PCAP/PCAPNG.</h1>
                    <p>
                        Acesse o ambiente para enviar capturas, acompanhar o processamento, investigar flows
                        e enriquecer alvos observados em tráfego de Internet Background Radiation.
                    </p>
                </div>

                <div className="auth-hero__details">
                    <div className="hero-stat">
                        <span>Capturas</span>
                        <strong>Upload, indexação e exploração PCAP/PCAPNG</strong>
                    </div>
                    <div className="hero-stat">
                        <span>Análise</span>
                        <strong>Pacotes, flows, protocolos e metadados operacionais</strong>
                    </div>
                    <div className="hero-stat">
                        <span>Enriquecimento</span>
                        <strong>ASN, geolocalização, reputação e contexto de alvos</strong>
                    </div>
                </div>
            </section>

            <section className="auth-card">
                <div className="auth-card__header">
                    <span className="panel__eyebrow">Acesso seguro</span>
                    <h2>{authMode === 'login' ? 'Entrar no IBRExplorer' : 'Recuperar acesso'}</h2>
                    <p>
                        {authMode === 'login'
                            ? 'Use sua conta institucional para acessar o workspace de análise.'
                            : 'Informe seu email para solicitar um token de redefinição de senha.'}
                    </p>
                </div>

                <div className="segmented-control" role="tablist" aria-label="Fluxos de autenticação">
                    <button
                        className={`segmented-control__item${authMode === 'login' ? ' is-active' : ''}`}
                        onClick={() => onAuthModeChange('login')}
                    >
                        Login
                    </button>
                    <button
                        className={`segmented-control__item${authMode === 'forgot' ? ' is-active' : ''}`}
                        onClick={() => onAuthModeChange('forgot')}
                    >
                        Esqueci a senha
                    </button>
                </div>

                {authMode === 'login' ? (
                    <form className="auth-form" onSubmit={handleLoginSubmit}>
                        <label className="field">
                            <span>Email</span>
                            <input
                                type="email"
                                value={credentials.email}
                                onChange={(event) => onCredentialChange('email', event.target.value)}
                                placeholder="admin@ibrexplorer.com"
                                autoComplete="email"
                            />
                        </label>

                        <label className="field">
                            <span>Senha</span>
                            <input
                                type="password"
                                value={credentials.password}
                                onChange={(event) => onCredentialChange('password', event.target.value)}
                                placeholder="Digite sua senha"
                                autoComplete="current-password"
                            />
                        </label>

                        {authError ? <p className="form-message form-message--error">{authError}</p> : null}

                        <button className="button button--primary" type="submit" disabled={loading}>
                            {loading ? 'Autenticando...' : 'Entrar no console'}
                        </button>
                    </form>
                ) : (
                    <form className="auth-form" onSubmit={handleForgotSubmit}>
                        <label className="field">
                            <span>Email</span>
                            <input
                                type="email"
                                value={forgotEmail}
                                onChange={(event) => onForgotEmailChange(event.target.value)}
                                placeholder="usuario@ibrexplorer.com"
                                autoComplete="email"
                            />
                        </label>

                        <p className="helper-copy">
                            Se houver uma conta ativa para este email, o sistema retornará as instruções de
                            redefinição disponíveis para o ambiente atual.
                        </p>

                        {authError ? <p className="form-message form-message--error">{authError}</p> : null}
                        {forgotMessage ? <p className="form-message form-message--success">{forgotMessage}</p> : null}

                        <button className="button button--primary" type="submit" disabled={loading}>
                            {loading ? 'Enviando...' : 'Solicitar redefinição'}
                        </button>
                    </form>
                )}

                <div className="auth-card__footer">
                    <span>Já possui token de redefinição?</span>
                    <button className="button button--ghost" onClick={() => onNavigate('/password-reset')}>
                        Abrir tela de redefinição
                    </button>
                </div>
            </section>
        </div>
    )
}

export default LoginPage
