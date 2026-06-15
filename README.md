# IBRExplorer

Plataforma web acadêmica para upload, processamento e exploração de arquivos PCAP/PCAPNG, com foco em análise de *
*Internet Background Radiation (IBR)**. Inspirada em ferramentas como Arkime/Moloch, mas baseada em arquivos submetidos
por usuário — não em captura ativa de rede.

## Funcionalidades

- **Upload e processamento assíncrono** — envio de PCAP/PCAPNG (até 150 MB); processamento ocorre em worker CLI isolado
  via `tshark`
- **Exploração hierárquica** — navegação por capturas → flows → pacotes, com telas de detalhe em todos os níveis
- **Filtros avançados** — por IP de origem/destino, porta, protocolo e intervalo temporal
- **Visualizações estatísticas** — distribuição de protocolos, top talkers, histograma de tamanho de pacote e timeline
  via Recharts
- **Enriquecimento dinâmico de IPs** — ao abrir um flow, os IPs de origem e destino são enriquecidos automaticamente ou
  sob demanda com:
    - MaxMind GeoLite2 City (geolocalização local, MMDB)
    - Team Cymru ASN (DNS TXT, IP → ASN)
    - rDNS (consulta PTR com timeout configurável)
    - Shodan InternetDB / Host API (portas, serviços, vulnerabilidades)
    - AbuseIPDB (score de reputação e relatórios)
    - Censys Platform API v3 (serviços, AS, localização)
- **Mapa geográfico** — visualização Leaflet com raio de precisão do GeoLite2 no painel de enriquecimento
- **Capturas públicas** — capturas podem ser marcadas como públicas e compartilhadas entre usuários da plataforma
- **Painel administrativo** — gerenciamento de usuários e integrações de enriquecimento (ativação, limites, credenciais)
- **Segurança** — criptografia AES-256-GCM para API keys armazenadas, rate limiting no Nginx, security headers

## Arquitetura

```
Upload PCAP/PCAPNG (≤ 150 MB)
  → API (PHP/Slim)
  → storage do arquivo bruto (S3 ou local)
  → fila de processamento (PostgreSQL)
  → worker assíncrono (PHP CLI + tshark)
  → parser em streaming → pcap_packet (em lote) → pcap_flow (SQL)
  → consultas pela interface web (React/Vite)
  → enriquecimento dinâmico ao abrir um flow
```

**Arquitetura híbrida:** metadados indexados no PostgreSQL para consultas rápidas; arquivo PCAP bruto preservado em
storage para análises profundas sob demanda.

### Stack

| Camada     | Tecnologia                               |
|------------|------------------------------------------|
| API        | PHP 8.3 + Slim Framework                 |
| Frontend   | React 18 + Vite (CSS custom, sem UI lib) |
| Worker     | PHP CLI + tshark                         |
| Banco      | PostgreSQL 18                            |
| Proxy      | Nginx                                    |
| Containers | Docker + Docker Compose                  |

## Requisitos

- **Docker** >= 24
- **Docker Compose** >= 2.20
- Porta `8080` livre (API/Nginx em dev) ou porta `80` livre (produção)
- Porta `5173` livre (frontend em dev)
- Banco de dados GeoIP MaxMind GeoLite2 City (`.mmdb`) — ver seção abaixo

## Instalação

### 1. Clone o repositório

```bash
git clone https://github.com/seu-usuario/TCC-IBRExplorer.git
cd TCC-IBRExplorer
```

### 2. Configure as variáveis de ambiente

Copie o exemplo e edite com suas configurações:

```bash
cp .env-example .env
```

Edite pelo menos as variáveis obrigatórias antes de subir os containers. Veja a referência completa na
seção [Variáveis de Ambiente](#variáveis-de-ambiente).

### 3. Obtenha o banco GeoIP (MaxMind GeoLite2)

O enriquecimento de geolocalização usa um banco MMDB local — ele **não está incluso no repositório** e precisa ser
obtido manualmente:

1. Crie uma conta gratuita em [maxmind.com](https://www.maxmind.com/en/geolite2/signup)
2. Baixe o arquivo **GeoLite2 City** (formato `.tar.gz` ou `.zip`)
3. Extraia o conteúdo — você terá uma pasta com a data no nome (ex: `GeoLite2-City_20260526`)
4. Mova essa pasta para dentro do diretório `geoip/` na raiz do projeto:

```
geoip/
  GeoLite2-City_20260526/
    GeoLite2-City.mmdb
```

A API detecta automaticamente o arquivo `.mmdb` mais recente com `City` no nome dentro de `geoip/`. Sem este banco, o
provider MaxMind ficará inativo, mas os demais provedores de enriquecimento continuam funcionando.

### 4. Inicie os containers (desenvolvimento)

```bash
docker compose up --build
```

Aguarde os healthchecks — o container `php` instala dependências via Composer na primeira execução.

Acesse:

- **Frontend**: http://localhost:5173
- **API**: http://localhost:8080

### 4a. Produção

```bash
docker compose -f docker-compose.prod.yml up --build -d
```

Em produção, o frontend é servido como build estático pelo Nginx na porta `80`. Não há container separado para o Vite.

### 5. Primeiro acesso

Após os containers iniciarem, o banco é criado e populado automaticamente via migrations. O usuário administrador é
configurado a partir das variáveis `ADMIN_EMAIL` e `ADMIN_PASSWORD` definidas no `.env` — a senha é computada em runtime
com o pepper configurado e **não é armazenada em texto plano**.

Faça login com as credenciais definidas nessas variáveis.

> O script de seed só define a senha se o administrador ainda não tiver uma. Em restarts subsequentes ele é ignorado
> automaticamente.

> Troque a senha após o primeiro acesso em **Perfil → Alterar Senha**.

## Variáveis de Ambiente

Crie um arquivo `.env` na raiz do projeto com as seguintes variáveis:

```dotenv
# ── Banco de Dados ──────────────────────────────────────────
POSTGRES_HOST=postgres
POSTGRES_PORT=5432
POSTGRES_USER=ibr
POSTGRES_PASSWORD=sua_senha_aqui
POSTGRES_DATABASE=ibrexplorer

# ── Segurança ───────────────────────────────────────────────
# Chave secreta para assinatura de JWT (qualquer string longa e aleatória)
TOKEN_KEY=troque_por_uma_chave_segura

# Emissor do JWT (identifica a aplicação)
TOKEN_ISSUER=ibrexplorer

# Pepper adicionado ao hash de senhas (string aleatória)
PASSWORD_PEPPER=troque_por_um_pepper_seguro

# Chave AES-256 para criptografia de API keys no banco (32 bytes em hex ou base64)
ENCRYPTION_KEY=troque_por_uma_chave_de_32_bytes

# ── Administrador inicial ────────────────────────────────────
# Usados apenas na primeira inicialização (quando o admin não tem senha)
# Após definida, alterações aqui não afetam o banco
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=troque_esta_senha

# ── E-mail ───────────────────────────────────────────────────
# URL base da aplicação usada nos links de reset de senha
APP_EMAIL_URL=http://localhost:5173

SMTP_HOST=smtp.exemplo.com
SMTP_USER=usuario@exemplo.com
SMTP_PASSWORD=senha_smtp

# ── Storage S3 (opcional) ────────────────────────────────────
# Deixe em branco para usar storage local
AWS_ACCESS_KEY=
AWS_SECRET_KEY=
AWS_REGION=
AWS_BUCKET=

# ── Worker ──────────────────────────────────────────────────
# Número de processos filhos do worker (default: 1)
PCAP_WORKER_CHILDREN=1

# ID do usuário dono das capturas processadas pelo worker (default: 1)
PCAP_WORKER_USER_ID=1

# Intervalo de polling em segundos (default: 30)
PCAP_WORKER_POLL_SECONDS=30

# Tamanho do lote de inserção de pacotes (default: 500)
PCAP_PACKET_BATCH_SIZE=500

# Caminho do binário tshark (default: tshark)
PCAP_TSHARK_BIN=tshark

# Timeout do tshark em segundos por pacote (default: 30)
PCAP_TSHARK_TIMEOUT_SECONDS=30

# Tempo máximo de processamento por captura em segundos (default: 900)
PCAP_WORKER_MAX_PROCESS_SECONDS=900

# Tempo para considerar uma captura travada em minutos (default: 60)
PCAP_WORKER_STALL_MINUTES=60

# ── Frontend ─────────────────────────────────────────────────
# URL da API consumida pelo frontend (build/runtime do Vite)
VITE_API_URL=http://localhost:8080

# ── Debug ────────────────────────────────────────────────────
DEBUG=false
```

### Variáveis obrigatórias

| Variável          | Descrição                                                                         |
|-------------------|-----------------------------------------------------------------------------------|
| `POSTGRES_*`      | Conexão com o banco PostgreSQL                                                    |
| `TOKEN_KEY`       | Chave de assinatura JWT — use uma string longa e aleatória                        |
| `PASSWORD_PEPPER` | Pepper de senhas — mude antes de criar usuários em produção                       |
| `ENCRYPTION_KEY`  | Chave AES-256-GCM para API keys — **obrigatória** se usar Shodan/AbuseIPDB/Censys |
| `ADMIN_EMAIL`     | E-mail do administrador criado no primeiro boot                                   |
| `ADMIN_PASSWORD`  | Senha do administrador criado no primeiro boot — troque após o primeiro acesso    |

### Storage de arquivos PCAP

Por padrão, os arquivos são armazenados localmente em `api/files/`. Para usar AWS S3, preencha as variáveis `AWS_*`.

## Configuração de Integrações de Enriquecimento

Após o primeiro acesso, acesse **Admin → Enrichment** para configurar as integrações opcionais:

| Integração       | Chave necessária            | Execução automática |
|------------------|-----------------------------|---------------------|
| MaxMind GeoLite2 | Não (banco local)           | Sim                 |
| Team Cymru ASN   | Não (DNS público)           | Sim                 |
| rDNS             | Não (DNS PTR)               | Sim                 |
| Shodan           | Opcional (Host API)         | Não                 |
| AbuseIPDB        | Sim                         | Não                 |
| Censys           | Sim (Personal Access Token) | Não                 |

Integrações com execução automática são disparadas ao abrir um flow. As demais exigem ação explícita do usuário na tela
de detalhe do flow.

## Fluxo de Uso

1. **Login** → acesse com as credenciais padrão ou as configuradas
2. **Upload** → envie um arquivo PCAP/PCAPNG (até 150 MB)
3. **Aguarde o processamento** — o worker processa o arquivo em background; acompanhe o status na listagem de capturas
4. **Explore a captura** — métricas, protocolos, top talkers e gráficos na aba *Visão Geral*
5. **Navegue pelos flows** — filtre por IP, porta ou protocolo na aba *Flows*
6. **Abra um flow** — veja os pacotes associados e o painel de enriquecimento com geolocalização, ASN, rDNS e reputação
7. **Inspecione pacotes** — detalhe completo de cada pacote, incluindo flags TCP, TTL e tamanho

## Comandos Úteis

```bash
# Subir em desenvolvimento (com hot reload no frontend)
docker compose up --build

# Subir em produção (background)
docker compose -f docker-compose.prod.yml up --build -d

# Ver logs do worker
docker compose logs -f worker

# Ver logs da API
docker compose logs -f php

# Acessar o banco diretamente
docker compose exec postgres psql -U ibr -d ibrexplorer

# Parar todos os containers
docker compose down

# Remover volumes (apaga dados do banco)
docker compose down -v
```

## Estrutura do Projeto

```
TCC-IBRExplorer/
├── api/                  # Backend PHP (Slim)
│   ├── bin/              # Entrypoints CLI (pcap-worker.php)
│   ├── config/           # Configuração da aplicação
│   ├── src/              # Código-fonte da API
│   └── public/           # index.php (entrada HTTP)
├── app/                  # Frontend React + Vite
│   └── src/
│       ├── components/   # Componentes reutilizáveis
│       ├── pages/        # Telas da aplicação
│       └── lib/          # Cliente HTTP e utilitários
├── docker/               # Dockerfiles e configurações
│   ├── nginx/            # Configs dev e prod do Nginx
│   ├── php/              # Dockerfile + entrypoint da API
│   └── worker/           # Dockerfile + entrypoint do worker
├── geoip/                # Banco MMDB do MaxMind (não versionado)
├── docker-compose.yml         # Ambiente de desenvolvimento
└── docker-compose.prod.yml    # Ambiente de produção
```

## Licença

Projeto acadêmico — Trabalho de Conclusão de Curso.
