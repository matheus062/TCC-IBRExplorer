import React from 'react'
import Uppy from '@uppy/core'
import {Dashboard} from '@uppy/react'
import AwsS3 from '@uppy/aws-s3'
import '@uppy/core/dist/style.css'
import '@uppy/dashboard/dist/style.css'

function App() {
    // TODO: Permitir apenas 1 arquivo por vez
    const uppy = new Uppy({
        autoProceed: true,
        restrictions: {
            maxFileSize: 100 * 1024 * 1024,               // TODO: Ajustar o limite de tamanho máximo do arquivo  Ex: 100MB
            // allowedFileTypes: ['.pcap', '.pcapng', '.zip', '.gz'] // TODO: Verificar a possibilidade de subir arquivos Zipados
            allowedFileTypes: ['.pcap', '.pcapng']
        }
    })

    uppy.use(AwsS3, {
        // async shouldUseMultipart(file) {
        //     // Use multipart only for files larger than 100MiB.
        //     return file.size > 100 * 2 ** 20;
        // },

        async getUploadParameters(file) {
            const [basename, ...extParts] = file.name.split('.')
            const ext = extParts.length ? extParts.pop() : ''
            const name = [basename, ...extParts].join('.')
            let response;
            // TODO: Remover XDEBUG_SESSION

            try {
                response = await fetch('https://api.ibr-explorer.localhost/pcap/file', {
                    method: 'POST',
                    headers: {
                        'Authorization': 'Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJtYXRoZXVzc2lsdmVzdHJlM0BlZHUudW5pdmFsaS5iciIsImlhdCI6MTc3Mzk0ODI4OCwiZXhwIjoxNzczOTg0Mjg4LCJ1aWQiOjJ9.9bJ_nedMrwsUqYpRVxTAEG_0Hsnn6Zvkah_cm7kh-Yg',
                        'Content-Type': 'application/json'
                    },
                    mode: 'cors',
                    body: JSON.stringify({
                        name: name,
                        ext: ext
                    })
                })
            } catch (e) {
                console.log(e.message)
            }

            // TODO: Fazer todas as verificacoes depois nessas funcoes, o front basicamente ta só em puro MOCK ainda ate terminar a API

            const data = await response.json()

            return {
                method: data.upload.method,
                url: data.upload.url,
                headers: data.upload.headers
            }
        }
    })

    uppy.on('upload-success', (file, response) => {
        console.log('✅ Arquivo enviado com sucesso:', file.name)
        console.log('📤 URL do S3:', response.uploadURL)

        // TODO: Após fazer o envio enviar confirmação ao front end, para que seja extraído do arquivo metadados.
        //  Alem disso também vai ser baixado do S3 o arquivo direto na API e manterá ele como cache durante 14 dias.
        //  Analisar bem essa parte de cache, porque arquivos muito grandes ou muitos arquivos talvez seria interessante
        //    no futuro implementar a quebra do arquivo em múltiplas partes.


    }).on('upload-error', (file, error, response) => {
        console.error('❌ Erro ao enviar arquivo:', file.name)
        console.error('📛 Detalhes do erro:', error)

        if (error?.cause) {
            console.error('🔎 Causa:', error.cause)
        }

        if (response) {
            console.error('📩 Resposta da AWS:', response)
        }

        if (error?.request) {
            console.error('🌐 Requisição com erro:', error.request)
        }
    })

    // useEffect(() => {
    //     return () => uppy && uppy.()
    // }, [])

    return (
        <div style={{maxWidth: 800, margin: '0 auto', padding: 20}}>
            <h1>Envio de Arquivos (S3 + Uppy)</h1>
            <Dashboard uppy={uppy} proudlyDisplayPoweredByUppy={false}/>
        </div>
    )
}

export default App


// 1. Enviar o tamanho e metadados do arquivo e implementar status do arquivo
// 2. Implementar multi part de envio
// 3. apos finalizar, avisar a API, que ira checar se bate os metadados antes de validar o arquivo
