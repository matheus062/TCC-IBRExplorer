function MetricCard({label, value, hint, accent = 'cyan'}) {
    return (
        <article className={`metric-card metric-card--${accent}`}>
            <span className="metric-card__label">{label}</span>
            <strong className="metric-card__value">{value}</strong>
            <span className="metric-card__hint">{hint}</span>
        </article>
    )
}

export default MetricCard
