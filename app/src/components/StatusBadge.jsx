function StatusBadge({status}) {
    return <span className={`status-badge status-badge--${status.tone}`}>{status.label}</span>
}

export default StatusBadge
