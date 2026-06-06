function EmptyState({title, copy, actionLabel, onAction}) {
    return (
        <div className="empty-state">
            <strong>{title}</strong>
            <p>{copy}</p>
            {actionLabel && onAction ? (
                <button className="button button--primary" onClick={onAction}>
                    {actionLabel}
                </button>
            ) : null}
        </div>
    )
}

export default EmptyState
