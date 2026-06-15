const CAPTURE_STATUS = {
    1: {label: 'Aguardando upload', tone: 'muted'},
    2: {label: 'Arquivo enviado', tone: 'info'},
    3: {label: 'Na fila', tone: 'warning'},
    4: {label: 'Processando', tone: 'warning'},
    5: {label: 'Erro', tone: 'danger'},
    6: {label: 'Processado', tone: 'success'},
}

const USER_ROLE = {
    1: 'System',
    2: 'Administrador',
    3: 'Suporte',
    4: 'Usuário',
}

const CAPTURE_VISIBILITY = {
    1: {label: 'Privada', tone: 'muted'},
    2: {label: 'Pública', tone: 'success'},
}

const PROTOCOL_LABEL = {
    1: 'ICMP',
    6: 'TCP',
    17: 'UDP',
    58: 'ICMPv6',
}

export function formatDateTime(value) {
    if (!value) {
        return 'Não informado'
    }

    return new Intl.DateTimeFormat('pt-BR', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(value))
}

export function formatDateParts(value) {
    if (!value) {
        return {date: 'Não informado', time: ''}
    }

    const parsed = new Date(value)

    return {
        date: new Intl.DateTimeFormat('pt-BR', {dateStyle: 'short'}).format(parsed),
        time: new Intl.DateTimeFormat('pt-BR', {timeStyle: 'short'}).format(parsed),
    }
}

export function formatRelativePercent(value) {
    if (value === undefined || value === null || value === '') {
        return '0%'
    }

    return `${Number(value).toFixed(0)}%`
}

export function formatBytes(value) {
    if (value === undefined || value === null || Number.isNaN(Number(value))) {
        return '0 B'
    }

    const units = ['B', 'KB', 'MB', 'GB']
    let amount = Number(value)
    let unitIndex = 0

    while (amount >= 1024 && unitIndex < units.length - 1) {
        amount /= 1024
        unitIndex += 1
    }

    return `${amount.toFixed(amount >= 10 || unitIndex === 0 ? 0 : 1)} ${units[unitIndex]}`
}

export function getCaptureStatus(status) {
    return CAPTURE_STATUS[status] ?? {label: 'Desconhecido', tone: 'muted'}
}

export function getCaptureVisibility(visibility) {
    return CAPTURE_VISIBILITY[visibility] ?? {label: 'Não informado', tone: 'muted'}
}

export function getProtocolLabel(protocol) {
    if (protocol === undefined || protocol === null || protocol === '') {
        return 'N/A'
    }

    return PROTOCOL_LABEL[protocol] ?? `IP ${protocol}`
}

export function formatEndpoint(ip, port) {
    if (!ip) {
        return 'N/A'
    }

    return port ? `${ip}:${port}` : ip
}

export function getUserRoleLabel(roles = []) {
    if (!Array.isArray(roles) || roles.length === 0) {
        return 'Sem nível de permissão'
    }

    return roles.map((role) => USER_ROLE[role.type] ?? `Nível ${role.type}`).join(', ')
}

export function getUserEmail(user) {
    return user?.email?.value ?? user?.email ?? 'Email não informado'
}

export function getUserInitials(name = '') {
    const parts = String(name || 'Usuário')
        .trim()
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)

    return parts.map((part) => part[0]?.toUpperCase()).join('') || 'U'
}

export function getUserAvatarSrc(user) {
    const image = user?.profileImage

    if (!image?.data) {
        return ''
    }

    const contentType = image.contentType?.value ?? image.contentType ?? 'image/png'

    return `data:${contentType};base64,${image.data}`
}

export function getFileLabel(file) {
    if (!file) {
        return 'Arquivo pendente'
    }

    const suffix = file.ext ? `.${file.ext}` : ''

    return `${file.altName ?? file.name ?? 'Sem nome'}${suffix}`
}

export function parseFileExtension(fileName) {
    const parts = fileName.split('.')

    if (parts.length < 2) {
        return ''
    }

    return parts.pop().toLowerCase()
}
