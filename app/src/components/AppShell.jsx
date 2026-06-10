import {useEffect, useState} from 'react'
import {getUserAvatarSrc, getUserEmail, getUserInitials} from '../lib/formatters'

const PAINEL_ITEMS = [
    {label: 'Dashboard', path: '/'},
]

const EXPLORAR_ITEMS = [
    {label: 'Minhas Capturas', path: '/captures'},
    {label: 'Upload', path: '/captures/upload'},
    {label: 'Capturas Públicas', path: '/captures/public'},
]

const ADMIN_NAV_ITEMS = [
    {label: 'Usuários', path: '/admin/users'},
    {label: 'Enriquecimento', path: '/admin/enrichment'},
]

function isActivePath(currentPath, targetPath) {
    if (targetPath === '/') {
        return currentPath === '/'
    }

    return currentPath === targetPath || currentPath.startsWith(`${targetPath}/`)
}

function NavSection({label, items, currentPath, onNavigate}) {
    return (
        <div className="app-nav__section">
            <span className="app-nav__section-label">{label}</span>
            {items.map((item) => (
                <button
                    key={item.path}
                    className={`app-nav__item${isActivePath(currentPath, item.path) ? ' is-active' : ''}${item.disabled ? ' is-disabled' : ''}`}
                    onClick={() => !item.disabled && onNavigate(item.path)}
                    disabled={item.disabled}
                >
                    {item.label}
                </button>
            ))}
        </div>
    )
}

function AppShell({currentPath, onNavigate, onLogout, session, notice, onDismiss, pageTitle, children}) {
    const [sidebarOpen, setSidebarOpen] = useState(false)

    useEffect(() => {
        setSidebarOpen(false)
    }, [currentPath])

    const isAdmin = session?.user?.roles?.some((role) => role.type === 2)
    const avatarSrc = getUserAvatarSrc(session?.user)
    const userName = session?.user?.name ?? 'Usuário autenticado'
    const userEmail = getUserEmail(session?.user)

    return (
        <div className={`app-shell${sidebarOpen ? ' sidebar-open' : ''}`}>
            {sidebarOpen && (
                <div
                    className="sidebar-overlay"
                    onClick={() => setSidebarOpen(false)}
                    role="presentation"
                />
            )}

            <aside className="app-sidebar">
                <div className="sidebar-top-row">
                    <button className="brand-lockup" onClick={() => onNavigate('/')}>
                        <span className="brand-lockup__title">IBRExplorer</span>
                        <span className="brand-lockup__copy">
                            Upload, indexação e exploração de capturas PCAP/PCAPNG.
                        </span>
                    </button>
                    <button
                        className="sidebar-close-btn"
                        onClick={() => setSidebarOpen(false)}
                        aria-label="Fechar menu"
                    >
                        ×
                    </button>
                </div>

                <nav className="app-nav" aria-label="Principal">
                    <NavSection label="Painel" items={PAINEL_ITEMS} currentPath={currentPath} onNavigate={onNavigate}/>
                    <NavSection label="Explorar" items={EXPLORAR_ITEMS} currentPath={currentPath}
                                onNavigate={onNavigate}/>
                    {isAdmin ? (
                        <NavSection label="Administração" items={ADMIN_NAV_ITEMS} currentPath={currentPath}
                                    onNavigate={onNavigate}/>
                    ) : null}
                </nav>

                <div className="sidebar-card">
                    <span className="sidebar-card__label">Sessão ativa</span>
                    <strong>{session?.user?.name ?? 'Usuário autenticado'}</strong>
                    <button className="button button--ghost" onClick={onLogout}>
                        Encerrar sessão
                    </button>
                </div>
            </aside>

            <div className="app-frame">
                <header className="app-topbar">
                    <button
                        className="hamburger-btn"
                        onClick={() => setSidebarOpen(true)}
                        aria-label="Abrir menu"
                    >
                        <span/>
                        <span/>
                        <span/>
                    </button>

                    <h1 className="app-topbar__title">{pageTitle ?? 'IBRExplorer'}</h1>

                    <button
                        className={`app-topbar__profile${isActivePath(currentPath, '/profile') ? ' is-active' : ''}`}
                        onClick={() => onNavigate('/profile')}
                        aria-label="Abrir perfil"
                    >
                        <span className="user-avatar user-avatar--compact" aria-hidden="true">
                            {avatarSrc ? <img src={avatarSrc} alt=""/> : getUserInitials(userName)}
                        </span>
                        <span className="app-topbar__profile-copy">
                            <span className="app-topbar__profile-name">{userName}</span>
                            <span className="app-topbar__profile-email">{userEmail}</span>
                        </span>
                    </button>
                </header>

                {notice ? (
                    <div className={`notice notice--${notice.tone ?? 'info'}`}>
                        <span>{notice.message}</span>
                        {onDismiss ? (
                            <button className="notice__dismiss" onClick={onDismiss} aria-label="Fechar">×</button>
                        ) : null}
                    </div>
                ) : null}

                <main className="app-content">{children}</main>
            </div>
        </div>
    )
}

export default AppShell
