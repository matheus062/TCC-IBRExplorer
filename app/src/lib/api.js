const API_BASE_URL = (import.meta.env.VITE_API_URL ?? 'http://localhost:8080').replace(/\/$/, '')

const DEFAULT_CAPTURE_FIELDS = [
    'id',
    'key',
    'status',
    'processed',
    'visibility',
    'file',
    'fileSize',
    'createdAt',
    'updatedAt',
    'uploadedAt',
    'processStartedAt',
    'processFinishedAt',
    'processAttempts',
    'processError',
    'pcap{id,key,startTimestamp,endTimestamp,packetsTotal,flowsTotal,capturedBytes,protocols,checksum,header}',
].join(',')

const DEFAULT_FLOW_FIELDS = [
    'id',
    'key',
    'pcap{id,key}',
    'flowKey',
    'srcIp',
    'dstIp',
    'srcPort',
    'dstPort',
    'protocol',
    'icmpType',
    'icmpCode',
    'packetCount',
    'bytesTotal',
    'startTimestamp',
    'endTimestamp',
].join(',')

const DEFAULT_PACKET_FIELDS = [
    'id',
    'key',
    'pcap{id,key}',
    'flowKey',
    'packetNumber',
    'timestamp',
    'offset',
    'capturedLen',
    'originalLen',
    'srcIp',
    'dstIp',
    'srcPort',
    'dstPort',
    'protocol',
    'ipVersion',
    'ttl',
    'ipLength',
    'payloadSize',
    'tcpFlags',
    'icmpType',
    'icmpCode',
].join(',')

const DEFAULT_ENRICHMENT_INTEGRATION_FIELDS = [
    'id',
    'key',
    'provider',
    'identifier',
    'name',
    'description',
    'enabled',
    'alwaysExecute',
    'requiresApiKey',
    'configSchema',
    'config',
    'resultExcludedFields',
    'dailyLimit',
    'weeklyLimit',
    'monthlyLimit',
    'dailyUsed',
    'weeklyUsed',
    'monthlyUsed',
    'dailyResetAt',
    'weeklyResetAt',
    'monthlyResetAt',
    'lastUsedAt',
    'lastError',
].join(',')

const DEFAULT_USER_FIELDS = [
    'id',
    'key',
    'entityStatus',
    'name',
    'email',
    'roles{type}',
    'createdAt',
    'updatedAt',
].join(',')

class ApiError extends Error {
    constructor(message, status, payload) {
        super(message)
        this.name = 'ApiError'
        this.status = status
        this.payload = payload
    }
}

function buildQuery(query = {}) {
    const searchParams = new URLSearchParams()

    for (const [key, value] of Object.entries(query)) {
        if (value === undefined || value === null || value === '') {
            continue
        }

        searchParams.set(key, String(value))
    }

    const queryString = searchParams.toString()

    return queryString ? `?${queryString}` : ''
}

function formatErrorPayload(payload) {
    if (!payload) {
        return 'Ocorreu um erro inesperado.'
    }

    if (typeof payload === 'string') {
        return payload
    }

    if (typeof payload.error === 'string') {
        return payload.error
    }

    if (typeof payload.message === 'string') {
        return payload.message
    }

    if (Array.isArray(payload)) {
        return payload.map(formatErrorPayload).join(' ')
    }

    if (typeof payload === 'object') {
        const messages = []

        for (const [field, value] of Object.entries(payload)) {
            if (typeof value === 'string') {
                messages.push(`${field}: ${value}`)
                continue
            }

            messages.push(formatErrorPayload(value))
        }

        return messages.filter(Boolean).join(' ')
    }

    return 'Ocorreu um erro inesperado.'
}

async function apiRequest(path, {method = 'GET', token, body, query, headers = {}} = {}) {
    const requestHeaders = new Headers({
        Accept: 'application/json',
        ...headers,
    })

    if (body !== undefined) {
        requestHeaders.set('Content-Type', 'application/json')
    }

    if (token) {
        requestHeaders.set('Authorization', `Bearer ${token}`)
    }

    const response = await fetch(`${API_BASE_URL}${path}${buildQuery(query)}`, {
        method,
        headers: requestHeaders,
        body: body !== undefined ? JSON.stringify(body) : undefined,
    })

    const contentType = response.headers.get('Content-Type') ?? ''
    const payload = contentType.includes('application/json')
        ? await response.json()
        : await response.text()

    if (!response.ok) {
        throw new ApiError(formatErrorPayload(payload), response.status, payload)
    }

    return payload
}

export {ApiError, API_BASE_URL}

export function createToken(credentials) {
    return apiRequest('/auth', {
        method: 'POST',
        body: credentials,
    })
}

export function forgotPassword(email) {
    return apiRequest('/password/forgot', {
        method: 'POST',
        body: {email},
    })
}

export function changePassword(password, token) {
    return apiRequest('/password/change', {
        method: 'PUT',
        token,
        body: {password},
    })
}

export function readProfile(token) {
    return apiRequest('/profile', {token})
}

export function updateProfileImage(token, payload) {
    return apiRequest('/profile/image', {
        method: 'PUT',
        token,
        body: payload,
    })
}

const DEFAULT_PUBLIC_CAPTURE_FIELDS = [
    'id',
    'key',
    'status',
    'processed',
    'visibility',
    'file',
    'fileSize',
    'createdAt',
    'createdBy{id,name}',
    'pcap{id,key,packetsTotal,flowsTotal,capturedBytes,protocols}',
].join(',')

export function listPublicPcapFiles(token, {limit = 24, page = 1, extraQuery = {}} = {}) {
    return apiRequest('/pcap/file/public', {
        token,
        query: {
            fields: DEFAULT_PUBLIC_CAPTURE_FIELDS,
            limit,
            page,
            ...extraQuery,
        },
    })
}

export function listPcapFiles(token, {limit = 24, page = 1, extraQuery = {}} = {}) {
    return apiRequest('/pcap/file', {
        token,
        query: {
            fields: DEFAULT_CAPTURE_FIELDS,
            limit,
            page,
            ...extraQuery,
        },
    })
}

export function readPcapFile(token, captureId) {
    return apiRequest(`/pcap/file/${captureId}`, {
        token,
        query: {
            fields: DEFAULT_CAPTURE_FIELDS,
        },
    })
}

export function readPcapFlow(token, flowId) {
    return apiRequest(`/pcap/flow/${flowId}`, {
        token,
        query: {fields: DEFAULT_FLOW_FIELDS},
    })
}

export function readPcapPacket(token, packetId) {
    return apiRequest(`/pcap/packet/${packetId}`, {
        token,
        query: {fields: DEFAULT_PACKET_FIELDS},
    })
}

export function listPcapFlows(token, {pcapId, limit = 12, page = 1, extraQuery = {}} = {}) {
    return apiRequest('/pcap/flow', {
        token,
        query: {
            fields: DEFAULT_FLOW_FIELDS,
            orderBy: 'packetCount DESC',
            limit,
            page,
            pcap: pcapId,
            ...extraQuery,
        },
    })
}

export function listPcapPackets(token, {pcapId, flowId, flowKey, limit = 16, page = 1, extraQuery = {}} = {}) {
    return apiRequest('/pcap/packet', {
        token,
        query: {
            fields: DEFAULT_PACKET_FIELDS,
            orderBy: 'packetNumber ASC',
            limit,
            page,
            pcap: pcapId,
            ...(flowKey ? {flowKey} : {flow: flowId}),
            ...extraQuery,
        },
    })
}

export function getPcapStats(token, captureId) {
    return apiRequest(`/pcap/file/${captureId}/stats`, {token})
}

export function retryPcapProcessing(token, captureKey) {
    return apiRequest(`/pcap/file/${captureKey}/retry`, {
        method: 'POST',
        token,
        body: {},
    })
}

export function getEnrichmentFlow(token, flowId) {
    return apiRequest(`/enrichment/flow/${flowId}`, {token})
}

export function executeEnrichmentFlow(token, flowId, providers = [], targetIds = []) {
    const body = {}
    if (providers.length > 0) body.providers = providers
    if (targetIds.length > 0) body.targets = targetIds

    return apiRequest(`/enrichment/flow/${flowId}`, {
        method: 'POST',
        token,
        body,
    })
}

export function listEnrichmentIntegrations(token, {limit = 50, page = 1, extraQuery = {}} = {}) {
    return apiRequest('/enrichment/integration', {
        token,
        query: {
            fields: DEFAULT_ENRICHMENT_INTEGRATION_FIELDS,
            orderBy: 'id ASC',
            limit,
            page,
            ...extraQuery,
        },
    })
}

export function updateEnrichmentIntegration(token, integrationId, payload) {
    return apiRequest(`/enrichment/integration/${integrationId}`, {
        method: 'PUT',
        token,
        body: payload,
    })
}

export function listUsers(token, {limit = 50, page = 1, extraQuery = {}} = {}) {
    return apiRequest('/user', {
        token,
        query: {
            fields: DEFAULT_USER_FIELDS,
            orderBy: 'name ASC',
            limit,
            page,
            ...extraQuery,
        },
    })
}

export function readUser(token, userId) {
    return apiRequest(`/user/${userId}`, {
        token,
        query: {fields: `${DEFAULT_USER_FIELDS},profileImage{data,contentType{value}}`},
    })
}

export function createUser(token, payload) {
    return apiRequest('/user', {
        method: 'POST',
        token,
        body: payload,
    })
}

export function updateUser(token, userId, payload) {
    return apiRequest(`/user/${userId}`, {
        method: 'PUT',
        token,
        body: payload,
    })
}

export function startPcapUpload(token, payload) {
    return apiRequest('/pcap/file', {
        method: 'POST',
        token,
        body: payload,
    })
}

export function confirmPcapUpload(token, confirmEndpoint, fileSize) {
    return apiRequest(confirmEndpoint, {
        method: 'POST',
        token,
        body: {fileSize},
    })
}

export async function uploadFileToSignedUrl(upload, file) {
    const response = await fetch(upload.url, {
        method: upload.method,
        headers: upload.headers,
        body: file,
    })

    if (!response.ok) {
        const errorText = await response.text()

        throw new ApiError(
            errorText || 'Falha ao enviar arquivo para o storage.',
            response.status,
            errorText
        )
    }

    return true
}

async function blobToBase64(blob) {
    return await new Promise((resolve, reject) => {
        const reader = new FileReader()

        reader.onload = () => {
            const result = typeof reader.result === 'string'
                ? reader.result.split(',').pop() ?? ''
                : ''

            resolve(result)
        }
        reader.onerror = () => reject(new ApiError('Falha ao codificar o chunk do arquivo.', 500, null))
        reader.readAsDataURL(blob)
    })
}

export async function uploadFileWithApiChunks(token, chunkEndpoint, file, chunkSize) {
    const totalChunks = Math.ceil(file.size / chunkSize)

    for (let chunkIndex = 0; chunkIndex < totalChunks; chunkIndex += 1) {
        const start = chunkIndex * chunkSize
        const chunkBlob = file.slice(start, Math.min(file.size, start + chunkSize))
        const chunkData = await blobToBase64(chunkBlob)

        await apiRequest(chunkEndpoint, {
            method: 'POST',
            token,
            body: {
                chunk: chunkIndex,
                chunks: totalChunks,
                data: chunkData,
            },
        })
    }
}

export async function uploadPcapFile(token, startResponse, file) {
    if (startResponse.storage?.mode === 's3') {
        await uploadFileToSignedUrl(startResponse.upload, file)
    } else {
        await uploadFileWithApiChunks(
            token,
            startResponse.storage.chunkEndpoint,
            file,
            startResponse.storage.chunkSize,
        )
    }

    return confirmPcapUpload(token, startResponse.storage.confirmEndpoint, file.size)
}
