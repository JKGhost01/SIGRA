# SIGRA · Monitor sísmico global e rede cidadã

**v1.3.1.3 · Conexão SGC recuperada** · build `20261119.1` · 19/11/2026

[Español](README.md) · [English](README.en.md) · [Português](README.pt.md) · [Français](README.fr.md) · [Deutsch](README.de.md) · [Italiano](README.it.md) · [日本語](README.ja.md) · [简体中文](README.zh-CN.md) · [العربية](README.ar.md)

Este guia apresenta o projeto e os primeiros passos em português. A [documentação completa](README.md) e os guias técnicos são mantidos em espanhol. Os comandos, caminhos e contratos da API preservam seus nomes originais. A interface web está disponível em espanhol e inglês.

## O que é o SIGRA

Aplicação PHP de código aberto que reúne terremotos publicados por **SGC, USGS, EMSC, GeoNet, GEOFON/GFZ e INGV** em um mapa pesquisável, com histórico, filtros, API JSON e relatos privados da população. O filtro de proximidade permite consultar eventos perto da localização escolhida.

A rede de sensores de celulares é **experimental**. O SIGRA não prevê terremotos, não garante alertas antecipados e não substitui autoridades ou sistemas oficiais. O cliente Android é desenvolvido separadamente em [SIGRA_MOBILE](https://github.com/JKGhost01/SIGRA_MOBILE).

## Recursos e fontes

- Mapa Leaflet, histórico, detalhes e filtros por local, fonte, magnitude e distância.
- SGC para a Colômbia; USGS, EMSC e GEOFON para cobertura global com limites próprios.
- GeoNet para a Nova Zelândia; INGV para a Itália e regiões próximas.
- API para web e Android; relatórios cidadãos privados e PWA instalável.
- Normalização e correlação entre fontes, com dados armazenados preservados durante falhas.

Os catálogos têm coberturas e frequências diferentes. Uma resposta HTTP válida não comprova atualização científica nem cobertura completa. Consulte [fontes e limites](docs/FUENTES_SISMICAS.md).

## Instalação

Requisitos: PHP 8.2+ com `curl`, `intl`, `mbstring` e `mysqli`, Composer 2 e MySQL/MariaDB. O CodeIgniter está incluído em `system/`; as dependências são instaladas a partir de `composer.lock`. Node 22+, Python 3 e PHP-CGI são usados nas verificações.

```bash
git clone https://github.com/JKGhost01/SIGRA.git
cd SIGRA
composer install
cp .env.example .env
php tools/setup-local.php
```

Crie um banco vazio e configure `database.default.*` e `app.baseURL` no `.env`. Depois:

```bash
php spark migrate --all
php -S 127.0.0.1:8080 -t public
```

Abra <http://127.0.0.1:8080/index.php/sismos>. Novas instalações devem expor somente `public/`. Siga o [procedimento completo de instalação](docs/INSTALLATION.md) para configurar chaves e permissões.

## Configuração e sincronização

| Configuração | Finalidade |
|---|---|
| `CI_ENVIRONMENT` | `development` localmente; `production` no servidor. |
| `app.baseURL` | URL pública com barra final. |
| `APP_API_TOKEN` | Token Bearer para rotas protegidas. |
| `database.default.*` | Conexão com o banco. |
| `encryption.key` | Chave privada de criptografia. |
| `quakes.*` | Fontes, filtros e intervalos. |
| `sigra.version`, `sigra.build` | Substituições opcionais dos metadados da versão. |

O mapa solicita atualizações automaticamente. A sincronização geral tem intervalo mínimo de cinco minutos; o SGC usa quinze minutos por host. Cache, bloqueios e pausas após erros são compartilhados entre processos. O cron é opcional. Para uma sincronização normal pelo terminal:

```bash
php spark sigra:sync
```

O comando respeita os intervalos e pode gravar eventos no banco. `/acerca-de#sources-health` mostra o estado registrado; atualizar esse painel não consulta os provedores. Preserve `writable/cache/providers` durante atualizações.

## API, privacidade e verificações

As rotas protegidas usam `Authorization: Bearer <APP_API_TOKEN>`. Consulte os contratos de [sismos](docs/API_SISMOS.md), [rede cidadã](docs/API_RED.md) e [OpenAPI](docs/api/openapi.yaml). A versão do contrato permanece `v1`.

A localização é opcional. Se usada como alternativa, a aproximação por IP depende de `ipapi.co`. Não publique tokens, credenciais nem relatos privados em issues.

```bash
composer test
composer analyse
python3 tools/check-git.py
```

As provas MySQL exigem um banco descartável selecionado explicitamente; não use produção.

## Atualização e documentação

A versão 1.3.1.3 restaura a compatibilidade HTTP com SGC. A prova local retornou HTTP 200 e 100 eventos normalizados; o usuário confirmou o funcionamento após implantar a correção. Esta revisão não adiciona migrações. Confira os [arquivos de implantação e reversão](docs/SGC_CONEXION_2026-11-19.md).

- [Operação e recuperação](docs/PRODUCCION.md)
- [Arquitetura](docs/architecture/README.md)
- [Versões](docs/VERSIONES.md) e [histórico](CHANGELOG.md)
- [Contribuir](CONTRIBUTING.md) e [código de conduta](CODE_OF_CONDUCT.md)
- [Licença MIT](LICENSE) e [avisos de terceiros](THIRD_PARTY_NOTICES.md)
