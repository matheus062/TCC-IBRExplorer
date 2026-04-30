import {defineConfig} from 'vite'
import react from '@vitejs/plugin-react'
import path from 'path'
import {fileURLToPath} from 'url'

const appDirectory = path.dirname(fileURLToPath(import.meta.url))

export default defineConfig({
    plugins: [react()],
    envDir: path.resolve(appDirectory, '..'),
})
