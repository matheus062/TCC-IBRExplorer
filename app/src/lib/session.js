const SESSION_KEY = 'ibr-explorer.session'

export function loadSession() {
    try {
        const serialized = window.localStorage.getItem(SESSION_KEY)

        if (!serialized) {
            return null
        }

        return JSON.parse(serialized)
    } catch {
        return null
    }
}

export function saveSession(session) {
    window.localStorage.setItem(SESSION_KEY, JSON.stringify(session))
}

export function clearSession() {
    window.localStorage.removeItem(SESSION_KEY)
}
