import {useState} from 'react'
import {formatBytes} from '../lib/formatters'

const MAX_FILE_SIZE = 150 * 1024 * 1024

function UploadPage({uploading, uploadError, uploadResult, onUpload}) {
    const [selectedFile, setSelectedFile] = useState(null)
    const [visibility, setVisibility] = useState('private')
    const [isDragging, setIsDragging] = useState(false)

    function handleFileChange(event) {
        const [file] = event.target.files ?? []
        setSelectedFile(file ?? null)
    }

    function handleDragEnter(event) {
        event.preventDefault()
        event.stopPropagation()
        setIsDragging(true)
    }

    function handleDragOver(event) {
        event.preventDefault()
        event.stopPropagation()
        event.dataTransfer.dropEffect = 'copy'
        setIsDragging(true)
    }

    function handleDragLeave(event) {
        event.preventDefault()
        event.stopPropagation()

        if (!event.currentTarget.contains(event.relatedTarget)) {
            setIsDragging(false)
        }
    }

    function handleDrop(event) {
        event.preventDefault()
        event.stopPropagation()
        setIsDragging(false)

        const [file] = event.dataTransfer.files ?? []
        setSelectedFile(file ?? null)
    }

    async function handleSubmit(event) {
        event.preventDefault()

        if (!selectedFile) {
            return
        }

        await onUpload(selectedFile, visibility)
    }

    const sizeError = selectedFile && selectedFile.size > MAX_FILE_SIZE
        ? 'O arquivo excede o limite de 150 MB.'
        : ''

    return (
        <div className="page-grid">
            <section className="panel panel--wide">
                <div className="panel__header">
                    <div>
                        <span className="panel__eyebrow">Envio de capturas</span>
                        <h2>Enviar arquivo PCAP</h2>
                    </div>
                </div>

                <form className="upload-layout" onSubmit={handleSubmit}>
                    <label
                        className={`upload-dropzone${isDragging ? ' is-dragging' : ''}`}
                        onDragEnter={handleDragEnter}
                        onDragOver={handleDragOver}
                        onDragLeave={handleDragLeave}
                        onDrop={handleDrop}
                    >
                        <input type="file" accept=".pcap,.pcapng" onChange={handleFileChange}/>
                        <span className="upload-dropzone__eyebrow">Selecionar arquivo</span>
                        <strong>{selectedFile ? selectedFile.name : 'Arraste ou escolha um PCAP/PCAPNG'}</strong>
                        <span>Limite de 150 MB por arquivo. Formatos aceitos: .pcap e .pcapng.</span>
                    </label>

                    <div className="upload-sidebar">
                        <label className="field">
                            <span>Visibilidade</span>
                            <select value={visibility} onChange={(event) => setVisibility(event.target.value)}>
                                <option value="private">Privada</option>
                                <option value="public">Pública</option>
                            </select>
                        </label>

                        <div className="upload-meta">
                            <span>Tamanho</span>
                            <strong>{selectedFile ? formatBytes(selectedFile.size) : 'Nenhum arquivo'}</strong>
                        </div>

                        <p className="helper-copy">
                            Após o envio, o arquivo é enfileirado para processamento automático.
                        </p>

                        {sizeError ? <p className="form-message form-message--error">{sizeError}</p> : null}
                        {uploadError ? <p className="form-message form-message--error">{uploadError}</p> : null}
                        {uploadResult ? <p className="form-message form-message--success">{uploadResult}</p> : null}

                        <button
                            className="button button--primary"
                            type="submit"
                            disabled={!selectedFile || Boolean(sizeError) || uploading}
                        >
                            {uploading ? 'Enviando...' : 'Solicitar upload e enviar ao storage'}
                        </button>
                    </div>
                </form>
            </section>
        </div>
    )
}

export default UploadPage
