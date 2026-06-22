import {startTransition, useCallback, useEffect, useState} from 'react'
import './App.css'
import AppShell from './components/AppShell'
import LoginPage from './pages/LoginPage'
import PasswordResetPage from './pages/PasswordResetPage'
import DashboardPage from './pages/DashboardPage'
import CapturesPage from './pages/CapturesPage'
import UploadPage from './pages/UploadPage'
import CaptureDetailPage from './pages/CaptureDetailPage'
import FlowDetailPage from './pages/FlowDetailPage'
import PacketDetailPage from './pages/PacketDetailPage'
import ProfilePage from './pages/ProfilePage'
import EnrichmentAdminPage from './pages/EnrichmentAdminPage'
import AdminUsersPage from './pages/AdminUsersPage'
import ExplorePage from './pages/ExplorePage'
import {
    ApiError,
    changePassword,
    createToken,
    forgotPassword,
    listPcapFiles,
    readPcapFile,
    readPcapFlow,
    readPcapPacket,
    readProfile,
    retryPcapProcessing,
    startPcapUpload,
    updateProfileImage,
    uploadPcapFile,
} from './lib/api'
import {clearSession, loadSession, saveSession} from './lib/session'
import {getFileLabel, getUserRoleLabel, parseFileExtension} from './lib/formatters'

function getPageTitle(route, capture) {
    switch (route.name) {
        case 'captures':
            return 'Capturas'
        case 'captures-public':
            return 'Capturas Públicas'
        case 'upload':
            return 'Novo upload'
        case 'capture-detail':
        case 'flow-detail':
        case 'packet-detail':
        case 'flow-packet-detail':
            return capture?.file ? getFileLabel(capture.file) : 'Detalhe da captura'
        case 'profile':
            return 'Perfil'
        case 'admin-users':
            return 'Usuários'
        case 'admin-enrichment':
            return 'Configurações de Enriquecimento'
        default:
            return 'Dashboard'
    }
}

function parseLocation() {
    const {pathname, search} = window.location

    if (pathname === '/login') {
        return {name: 'login'}
    }

    if (pathname === '/password-reset') {
        const params = new URLSearchParams(search)

        return {
            name: 'password-reset',
            token: params.get('token') ?? '',
        }
    }

    if (pathname === '/captures') {
        return {name: 'captures'}
    }

    if (pathname === '/captures/public') {
        return {name: 'captures-public'}
    }

    if (pathname === '/captures/upload') {
        return {name: 'upload'}
    }

    const flowPacketDetailMatch = pathname.match(/^\/captures\/(\d+)\/flows\/(\d+)\/packets\/(\d+)$/)
    if (flowPacketDetailMatch) {
        return {
            name: 'flow-packet-detail',
            captureId: flowPacketDetailMatch[1],
            flowId: flowPacketDetailMatch[2],
            packetId: flowPacketDetailMatch[3],
        }
    }

    const flowDetailMatch = pathname.match(/^\/captures\/(\d+)\/flows\/(\d+)$/)
    if (flowDetailMatch) {
        return {
            name: 'flow-detail',
            captureId: flowDetailMatch[1],
            flowId: flowDetailMatch[2],
        }
    }

    const flowsTabMatch = pathname.match(/^\/captures\/(\d+)\/flows$/)
    if (flowsTabMatch) {
        return {
            name: 'capture-detail',
            captureId: flowsTabMatch[1],
            tab: 'flows',
        }
    }

    const packetDetailMatch = pathname.match(/^\/captures\/(\d+)\/packets\/(\d+)$/)
    if (packetDetailMatch) {
        return {
            name: 'packet-detail',
            captureId: packetDetailMatch[1],
            packetId: packetDetailMatch[2],
        }
    }

    const packetsTabMatch = pathname.match(/^\/captures\/(\d+)\/packets$/)
    if (packetsTabMatch) {
        return {
            name: 'capture-detail',
            captureId: packetsTabMatch[1],
            tab: 'packets',
        }
    }

    if (pathname.startsWith('/captures/')) {
        return {
            name: 'capture-detail',
            captureId: pathname.split('/')[2],
            tab: 'overview',
        }
    }

    if (pathname === '/profile') {
        return {name: 'profile'}
    }

    if (pathname === '/admin/users') {
        return {name: 'admin-users'}
    }

    if (pathname === '/admin/enrichment') {
        return {name: 'admin-enrichment'}
    }

    return {name: 'dashboard'}
}

function navigateTo(path, replace = false) {
    const method = replace ? 'replaceState' : 'pushState'
    window.history[method]({}, '', path)
}

function App() {
    const [route, setRoute] = useState(() => parseLocation())
    const [session, setSession] = useState(() => {
        const currentSession = loadSession()

        if (!currentSession) {
            return null
        }

        return {
            ...currentSession,
            userRoleLabel: getUserRoleLabel(currentSession.user?.roles),
        }
    })
    const [notice, setNotice] = useState(null)
    const [authMode, setAuthMode] = useState('login')
    const [credentials, setCredentials] = useState({email: '', password: ''})
    const [forgotEmail, setForgotEmail] = useState('')
    const [passwordForm, setPasswordForm] = useState({
        token: route.name === 'password-reset' ? route.token : '',
        password: '',
        confirmPassword: '',
    })
    const [authLoading, setAuthLoading] = useState(false)
    const [authError, setAuthError] = useState('')
    const [forgotMessage, setForgotMessage] = useState('')
    const [passwordSuccess, setPasswordSuccess] = useState('')
    const [capturesState, setCapturesState] = useState({
        items: [],
        total: 0,
        isLoading: false,
        error: '',
    })
    const [captureDetail, setCaptureDetail] = useState({
        item: null,
        isLoading: false,
        error: '',
    })
    const [flowDetail, setFlowDetail] = useState({
        item: null,
        isLoading: false,
        error: '',
    })
    const [packetDetail, setPacketDetail] = useState({
        item: null,
        isLoading: false,
        error: '',
    })
    const [flowListState, setFlowListState] = useState({})
    const [uploading, setUploading] = useState(false)
    const [uploadError, setUploadError] = useState('')
    const [uploadResult, setUploadResult] = useState('')
    const [profileImageLoading, setProfileImageLoading] = useState(false)

    useEffect(() => {
        function handlePopState() {
            startTransition(() => {
                setRoute(parseLocation())
            })
        }

        window.addEventListener('popstate', handlePopState)

        return () => window.removeEventListener('popstate', handlePopState)
    }, [])

    useEffect(() => {
        if (route.name === 'password-reset') {
            setPasswordForm((current) => ({
                ...current,
                token: route.token ?? current.token,
            }))
        }
    }, [route])

    useEffect(() => {
        const isPublicRoute = route.name === 'login' || route.name === 'password-reset'

        if (!session && !isPublicRoute) {
            handleNavigate('/login', true)
        }

        if (session && route.name === 'login') {
            handleNavigate('/', true)
        }
    }, [route.name, session])

    const handleApiFailure = useCallback((error, fallbackMessage) => {
        if (error instanceof ApiError && error.status === 401 && session) {
            setSessionState(null)
            setNotice({tone: 'warning', message: 'Sua sessão expirou. Faça login novamente.'})
            handleNavigate('/login', true)

            return error.message
        }

        return error instanceof Error ? error.message : fallbackMessage
    }, [session])

    const loadCapturesEffect = useCallback(async () => {
        setCapturesState((current) => ({...current, isLoading: true, error: ''}))

        try {
            const response = await listPcapFiles(session.token)

            setCapturesState({
                items: response.entities ?? [],
                total: response.total ?? 0,
                isLoading: false,
                error: '',
            })
        } catch (error) {
            setCapturesState((current) => ({
                ...current,
                isLoading: false,
                error: handleApiFailure(error, 'Não foi possível carregar as capturas.'),
            }))
        }
    }, [session?.token])

    const loadFlowDetailEffect = useCallback(async (flowId) => {
        setFlowDetail({item: null, isLoading: true, error: ''})

        try {
            const flow = await readPcapFlow(session.token, flowId)

            setFlowDetail({item: flow, isLoading: false, error: ''})
        } catch (error) {
            setFlowDetail({
                item: null,
                isLoading: false,
                error: handleApiFailure(error, 'Não foi possível carregar o flow.'),
            })
        }
    }, [session?.token])

    const loadPacketDetailEffect = useCallback(async (packetId) => {
        setPacketDetail({item: null, isLoading: true, error: ''})

        try {
            const packet = await readPcapPacket(session.token, packetId)

            setPacketDetail({item: packet, isLoading: false, error: ''})
        } catch (error) {
            setPacketDetail({
                item: null,
                isLoading: false,
                error: handleApiFailure(error, 'Não foi possível carregar o pacote.'),
            })
        }
    }, [handleApiFailure, session?.token])

    const loadCaptureDetailEffect = useCallback(async (captureId) => {
        setCaptureDetail({
            item: null,
            isLoading: true,
            error: '',
        })

        try {
            const capture = await readPcapFile(session.token, captureId)

            setCaptureDetail({
                item: capture,
                isLoading: false,
                error: '',
            })
        } catch (error) {
            setCaptureDetail({
                item: null,
                isLoading: false,
                error: handleApiFailure(error, 'Não foi possível carregar o detalhe da captura.'),
            })
        }
    }, [handleApiFailure, session?.token])

    useEffect(() => {
        if (!session?.token) {
            setCapturesState((current) => ({
                ...current,
                items: [],
                total: 0,
                error: '',
            }))

            return
        }

        void loadCapturesEffect()
    }, [loadCapturesEffect, session?.token])

    useEffect(() => {
        const isRelevantRoute = route.name === 'capture-detail'
            || route.name === 'flow-detail'
            || route.name === 'packet-detail'
            || route.name === 'flow-packet-detail'

        if (!session?.token || !isRelevantRoute || !route.captureId) {
            return
        }

        void loadCaptureDetailEffect(route.captureId)
    }, [loadCaptureDetailEffect, route.name, route.captureId, session?.token])

    useEffect(() => {
        const isRelevantRoute = route.name === 'flow-detail' || route.name === 'flow-packet-detail'

        if (!session?.token || !isRelevantRoute || !route.flowId) {
            return
        }

        void loadFlowDetailEffect(route.flowId)
    }, [loadFlowDetailEffect, route.name, route.flowId, session?.token])

    useEffect(() => {
        const isRelevantRoute = route.name === 'packet-detail' || route.name === 'flow-packet-detail'

        if (!session?.token || !isRelevantRoute || !route.packetId) {
            return
        }

        void loadPacketDetailEffect(route.packetId)
    }, [loadPacketDetailEffect, route.name, route.packetId, session?.token])

    useEffect(() => {
        if (!session?.token) {
            return
        }

        async function loadCurrentProfile() {
            try {
                const user = await readProfile(session.token)

                setSessionState({
                    ...session,
                    user,
                })
            } catch (error) {
                handleApiFailure(error, 'Não foi possível carregar o perfil.')
            }
        }

        void loadCurrentProfile()
    }, [session?.token])

    function handleNavigate(path, replace = false) {
        navigateTo(path, replace)

        startTransition(() => {
            setRoute(parseLocation())
        })
    }

    function setSessionState(nextSession) {
        if (!nextSession) {
            clearSession()
            setSession(null)
            return
        }

        const normalized = {
            ...nextSession,
            userRoleLabel: getUserRoleLabel(nextSession.user?.roles),
        }

        saveSession(normalized)
        setSession(normalized)
    }

    async function loadCaptures() {
        await loadCapturesEffect()
    }

    const handleFlowListStateChange = useCallback((captureId, nextState) => {
        setFlowListState((current) => ({
            ...current,
            [captureId]: typeof nextState === 'function'
                ? nextState(current[captureId])
                : nextState,
        }))
    }, [])

    async function handleLogin() {
        setAuthLoading(true)
        setAuthError('')
        setForgotMessage('')

        try {
            const response = await createToken(credentials)

            setSessionState(response)
            setNotice({tone: 'success', message: 'Sessão autenticada com sucesso.'})
            setCredentials({email: '', password: ''})
            handleNavigate('/', true)
        } catch (error) {
            setAuthError(handleApiFailure(error, 'Não foi possível autenticar.'))
        } finally {
            setAuthLoading(false)
        }
    }

    async function handleForgotPassword() {
        setAuthLoading(true)
        setAuthError('')
        setForgotMessage('')

        try {
            const response = await forgotPassword(forgotEmail)
            const message = response.token
                ? `Token retornado pela API: ${response.token}`
                : response.message

            setForgotMessage(message)
        } catch (error) {
            setAuthError(handleApiFailure(error, 'Não foi possível solicitar a redefinição.'))
        } finally {
            setAuthLoading(false)
        }
    }

    async function handlePasswordChange(tokenOverride = null) {
        setAuthLoading(true)
        setAuthError('')
        setPasswordSuccess('')

        if (passwordForm.password.length < 8) {
            setAuthLoading(false)
            setAuthError('A senha deve ter no mínimo 8 caracteres.')

            return
        }

        if (!/[^A-Za-z0-9]/.test(passwordForm.password)) {
            setAuthLoading(false)
            setAuthError('A senha deve conter ao menos um caractere especial.')

            return
        }

        if (passwordForm.password !== passwordForm.confirmPassword) {
            setAuthLoading(false)
            setAuthError('A confirmação da senha precisa ser idêntica ao valor principal.')

            return
        }

        try {
            await changePassword(passwordForm.password, tokenOverride ?? session?.token ?? passwordForm.token)
            setPasswordSuccess('Senha alterada com sucesso.')
            setPasswordForm((current) => ({
                ...current,
                password: '',
                confirmPassword: '',
            }))
        } catch (error) {
            setAuthError(handleApiFailure(error, 'Não foi possível alterar a senha.'))
        } finally {
            setAuthLoading(false)
        }
    }

    async function handleProfileImageChange(payload) {
        setProfileImageLoading(true)
        setAuthError('')

        try {
            const response = await updateProfileImage(session.token, payload)

            setSessionState({
                ...session,
                user: response.user,
            })
            setNotice({tone: 'success', message: response.message ?? 'Foto de perfil atualizada.'})
        } catch (error) {
            setAuthError(handleApiFailure(error, 'Não foi possível atualizar a foto de perfil.'))
        } finally {
            setProfileImageLoading(false)
        }
    }

    async function handleUpload(file, visibility) {
        setUploading(true)
        setUploadError('')
        setUploadResult('')

        const extension = parseFileExtension(file.name)
        const baseName = extension
            ? file.name.slice(0, file.name.length - extension.length - 1)
            : file.name

        if (!extension || !['pcap', 'pcapng'].includes(extension)) {
            setUploading(false)
            setUploadError('Selecione um arquivo PCAP ou PCAPNG válido.')

            return
        }

        try {
            const startResponse = await startPcapUpload(session.token, {
                name: baseName,
                ext: extension,
                visibility,
            })

            const confirmResponse = await uploadPcapFile(session.token, startResponse, file)
            const storageLabel = startResponse.storage?.mode === 's3'
                ? 'S3'
                : 'storage local com chunks'

            setUploadResult(
                confirmResponse.message
                ?? `Upload concluído com sucesso para o registro #${startResponse.id} usando ${storageLabel}.`
            )
            setNotice({tone: 'success', message: 'Arquivo enviado e confirmado para processamento.'})
            await loadCaptures()
        } catch (error) {
            setUploadError(handleApiFailure(error, 'Falha ao enviar o arquivo.'))
        } finally {
            setUploading(false)
        }
    }

    async function handleRetryProcessing(capture) {
        if (!capture?.key) {
            setNotice({tone: 'warning', message: 'Captura sem chave para reprocessamento.'})

            return
        }

        try {
            const response = await retryPcapProcessing(session.token, capture.key)

            setNotice({
                tone: 'success',
                message: response.message ?? 'Captura reenfileirada para processamento.',
            })
            await loadCaptures()
            await loadCaptureDetailEffect(capture.id)
        } catch (error) {
            setNotice({
                tone: 'warning',
                message: handleApiFailure(error, 'Não foi possível reenfileirar a captura.'),
            })
        }
    }

    function handleLogout() {
        setSessionState(null)
        setNotice({tone: 'info', message: 'Sessão encerrada.'})
        handleNavigate('/login', true)
    }

    function renderPublicRoute() {
        if (route.name === 'password-reset') {
            return (
                <PasswordResetPage
                    password={passwordForm.password}
                    confirmPassword={passwordForm.confirmPassword}
                    loading={authLoading}
                    error={authError}
                    successMessage={passwordSuccess}
                    onPasswordChange={(field, value) => setPasswordForm((current) => ({...current, [field]: value}))}
                    onNavigate={handleNavigate}
                    onSubmit={() => handlePasswordChange(passwordForm.token)}
                />
            )
        }

        return (
            <LoginPage
                authMode={authMode}
                credentials={credentials}
                forgotEmail={forgotEmail}
                loading={authLoading}
                authError={authError}
                forgotMessage={forgotMessage}
                onAuthModeChange={setAuthMode}
                onCredentialChange={(field, value) => setCredentials((current) => ({...current, [field]: value}))}
                onForgotEmailChange={setForgotEmail}
                onLogin={handleLogin}
                onForgotPassword={handleForgotPassword}
                onNavigate={handleNavigate}
            />
        )
    }

    function renderPrivateRoute() {
        switch (route.name) {
            case 'captures':
                return (
                    <CapturesPage
                        token={session.token}
                        onNavigate={handleNavigate}
                        onApiFailure={handleApiFailure}
                    />
                )
            case 'captures-public':
                return (
                    <ExplorePage
                        token={session.token}
                        onNavigate={handleNavigate}
                        onApiFailure={handleApiFailure}
                    />
                )
            case 'upload':
                return (
                    <UploadPage
                        uploading={uploading}
                        uploadError={uploadError}
                        uploadResult={uploadResult}
                        onUpload={handleUpload}
                    />
                )
            case 'capture-detail':
                return (
                    <CaptureDetailPage
                        key={route.captureId}
                        token={session.token}
                        capture={captureDetail.item}
                        isLoading={captureDetail.isLoading}
                        error={captureDetail.error}
                        activeTab={route.tab ?? 'overview'}
                        captureId={route.captureId}
                        flowListState={flowListState[route.captureId]}
                        onFlowListStateChange={handleFlowListStateChange}
                        onNavigate={handleNavigate}
                        onRetryProcessing={handleRetryProcessing}
                        onApiFailure={handleApiFailure}
                    />
                )
            case 'profile':
                return (
                    <ProfilePage
                        session={session}
                        passwordForm={passwordForm}
                        loading={authLoading}
                        error={authError}
                        successMessage={passwordSuccess}
                        onPasswordFieldChange={(field, value) => setPasswordForm((current) => ({
                            ...current,
                            [field]: value
                        }))}
                        onChangePassword={handlePasswordChange}
                        onProfileImageChange={handleProfileImageChange}
                        profileImageLoading={profileImageLoading}
                        onLogout={handleLogout}
                    />
                )
            case 'flow-detail':
                return (
                    <FlowDetailPage
                        token={session.token}
                        flow={flowDetail.item}
                        capture={captureDetail.item}
                        isLoading={flowDetail.isLoading || captureDetail.isLoading}
                        error={flowDetail.error || captureDetail.error}
                        captureId={route.captureId}
                        onNavigate={handleNavigate}
                        onApiFailure={handleApiFailure}
                    />
                )
            case 'packet-detail':
                return (
                    <PacketDetailPage
                        key={route.packetId}
                        token={session.token}
                        packet={packetDetail.item}
                        capture={captureDetail.item}
                        flow={null}
                        isLoading={packetDetail.isLoading || captureDetail.isLoading}
                        error={packetDetail.error || captureDetail.error}
                        captureId={route.captureId}
                        flowId={null}
                        onNavigate={handleNavigate}
                        onApiFailure={handleApiFailure}
                    />
                )
            case 'flow-packet-detail':
                return (
                    <PacketDetailPage
                        key={route.packetId}
                        token={session.token}
                        packet={packetDetail.item}
                        capture={captureDetail.item}
                        flow={flowDetail.item}
                        isLoading={packetDetail.isLoading || captureDetail.isLoading || flowDetail.isLoading}
                        error={packetDetail.error || captureDetail.error || flowDetail.error}
                        captureId={route.captureId}
                        flowId={route.flowId}
                        onNavigate={handleNavigate}
                        onApiFailure={handleApiFailure}
                    />
                )
            case 'admin-users':
                return (
                    <AdminUsersPage
                        token={session.token}
                        onApiFailure={handleApiFailure}
                    />
                )
            case 'admin-enrichment':
                return (
                    <EnrichmentAdminPage
                        token={session.token}
                        onApiFailure={handleApiFailure}
                    />
                )
            default:
                return (
                    <DashboardPage
                        captures={capturesState.items}
                        capturesState={capturesState}
                        session={session}
                        onNavigate={handleNavigate}
                        onRefresh={loadCaptures}
                    />
                )
        }
    }

    if (!session) {
        return renderPublicRoute()
    }

    return (
        <AppShell
            currentPath={window.location.pathname}
            onNavigate={handleNavigate}
            onLogout={handleLogout}
            session={session}
            notice={notice}
            onDismiss={() => setNotice(null)}
            pageTitle={getPageTitle(route, captureDetail.item)}
        >
            {renderPrivateRoute()}
        </AppShell>
    )
}

export default App
