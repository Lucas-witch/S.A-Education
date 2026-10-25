# S.A Education Android

Aplicativo React Native com Expo SDK 57, TypeScript estrito e Expo Router. O projeto
PHP permanece na raiz; esta pasta contém somente o cliente Android.

## Requisitos

- Node.js LTS compatível com Expo SDK 57 e npm.
- Android Studio com Android SDK/emulador para executar uma instalação nativa local.
- Conta Expo/EAS para builds remotos e credenciais de assinatura gerenciadas pelo EAS.
- Backend PHP/MySQL em execução com as migrações da API mobile aplicadas.

## Desenvolvimento

```powershell
Set-Location mobile
Copy-Item .env.example .env
npm ci
npm start
```

Inicie um emulador Android e pressione `a` no Expo CLI. O endereço `10.0.2.2` só funciona no emulador Android padrão.
Em um dispositivo físico, configure `EXPO_PUBLIC_API_BASE_URL` com o endereço IP
alcançável do servidor na rede local. A URL precisa terminar em `/api/v1`; use
`http://` somente para desenvolvimento local.

`EXPO_PUBLIC_API_BASE_URL` é uma configuração pública de destino, não um segredo.
Credenciais do banco, chaves privadas e tokens nunca devem ser colocados em variáveis
`EXPO_PUBLIC_*`. O cliente exige HTTPS nos bundles de produção. HTTP é habilitado
apenas no ambiente local de desenvolvimento quando `EXPO_PUBLIC_ALLOW_HTTP_API=true`;
o manifesto Android de preview e produção mantém tráfego em texto claro desabilitado.

## API e sessão

O aplicativo usa somente os contratos documentados em `../Markdowns/API_MOBILE.md`.
Login, renovação de refresh token, consulta da conta e logout usam
`/api/v1/auth.php`. O refresh token fica no `expo-secure-store`; o access token
permanece em memória. Chamadas autenticadas renovam o access token em resposta a
`401` e repetem a solicitação uma vez. Timeout, falhas de rede, envelopes inválidos e
erros HTTP são tratados no serviço central.

### Estado das áreas mobile

- **Início e perfil:** exibem os dados da conta autenticada. Edição de perfil e
  configurações ainda não está integrada.
- **Salas:** pesquisa e paginação usam o endpoint versionado `resource=salas`;
  criação, entrada, membros e moderação ainda não foram integrados.
- **Biblioteca:** pesquisa paginada usa `resource=pesquisa` e filtros reais de tipo.
  Leitura detalhada, anotações, progresso e empréstimos ainda não estão conectados à
  interface.
- **Ranking:** consulta o endpoint público com a filtragem de privacidade existente no
  servidor.
- **Feed:** a API ainda não tem uma operação mobile de listagem de publicações.
  Comentários e ações não são apresentados como disponíveis sem essa listagem.
- **Questões:** catálogo mobile, execução de quizzes, tentativas e correção ainda não
  estão disponíveis.
- **Cadastro e recuperação de acesso:** não há contratos de API mobile para esses
  fluxos; permanecem na versão web.
- **Área institucional:** a autenticação identifica a conta, mas as funções do portal
  institucional ainda não foram integradas. Aprovação e permissões continuam no
  servidor.

As áreas pendentes exibem o motivo da indisponibilidade, sem dados demonstrativos.
Os testes locais validam contratos, parsing, estados e configuração; login válido ou
inválido, operações autenticadas e permissões ainda precisam de homologação contra um
backend real com as migrações aplicadas.

## Qualidade e build

```powershell
npm run typecheck
npm run lint
npm run format:check
npm test
npx expo-doctor
```

Crie um APK de homologação e um APK de distribuição com EAS Build:

```powershell
npx eas-cli@25.3.0 build --platform android --profile preview
npx eas-cli@25.3.0 build --platform android --profile production
```

Configure `EXPO_PUBLIC_API_BASE_URL` nos ambientes EAS `preview` e `production`
antes do build; endpoints publicados devem usar HTTPS. O identificador Android
`com.saeducation.mobile` e o slug já são específicos deste aplicativo; confirme o
namespace antes da primeira publicação, pois ele não deve ser alterado após a
distribuição.

## Preparação EAS e build APK

O repositório não contém `projectId` do EAS nem uma URL de backend de produção.
Esses valores precisam ser configurados pela equipe antes de um build distribuível;
os comandos abaixo não geram APK de produção válido até essa configuração existir.

1. Instale o Node.js 24 LTS, entre na pasta `mobile` e autentique o EAS:

   ```powershell
   npx eas-cli@25.3.0 login
   npx eas-cli@25.3.0 init
   ```

   Confirme e versione a associação EAS (`extra.eas.projectId`) criada no arquivo
   `app.config.ts`. O projeto Android usa `com.saeducation.mobile`; confirme que o
   identificador está reservado para este app antes do primeiro build distribuído.
2. No painel EAS, configure `EXPO_PUBLIC_API_BASE_URL` nos ambientes **preview** e
   **production**, apontando para o backend público de cada ambiente, terminado em
   `/api/v1`. O perfil production valida HTTPS e rejeita localhost, IPs privados,
   credenciais embutidas, query string e fragmento. Não use a URL de `.env.example`.
3. Configure as credenciais Android gerenciadas pelo EAS em **Credentials** ou com
   `npx eas-cli@25.3.0 credentials --platform android`. Não baixe, copie ou versione a chave
   de assinatura. A mesma chave deve ser preservada para permitir instalação de
   atualizações sobre a versão já instalada.
4. Gere e revise um APK de homologação:

   ```powershell
   npx eas-cli@25.3.0 build --platform android --profile preview
   ```

5. Depois de validar o servidor de homologação, gere o APK de distribuição:

   ```powershell
   $env:APP_ENV = "production"
   $env:EXPO_PUBLIC_API_BASE_URL = Read-Host "Informe a URL HTTPS pública real do backend"
   npx expo config --type public --json
   npx eas-cli@25.3.0 build --platform android --profile production
   ```

   Informe a URL HTTPS real **e idêntica à variável do ambiente production do EAS**.
   A configuração dinâmica interrompe um build production se a
   URL estiver ausente ou for local/privada. Não passe credenciais nessa variável;
   a URL é configuração pública.

O perfil `preview` e o `production` geram APK (`android.buildType: apk`). A versão do
app é mantida pelo EAS no modo remoto e o `versionCode` é incrementado no perfil
production. Builds locais nativos também são possíveis após instalar Android Studio,
Android SDK, Java compatível e executar `npx expo prebuild --platform android` e
`npx expo run:android`; o projeto gerado em `android/` é descartável e está ignorado
pelo Git. Para releases, prefira o build EAS para manter a chave fora da máquina e do
repositório.

## CI e publicação no GitHub Releases

O workflow [mobile-android.yml](../.github/workflows/mobile-android.yml) executa
TypeScript, lint, formatação, testes mobile, Expo Doctor e os testes PHP em pull
requests e pushes que alteram o app ou o contrato do catálogo. Ele não envia APK à
Play Store.

Para habilitar o build de Release:

- Configure a variável de repositório GitHub `EXPO_PUBLIC_API_BASE_URL` com a URL
  HTTPS pública do backend production.
- Configure o segredo de repositório `EAS_TOKEN` com um token de automação Expo.
- Garanta que o mesmo endpoint está cadastrado no ambiente `production` do projeto
  EAS, e que o projeto EAS está associado pelo `projectId` versionado.
- Crie e envie uma tag de versão, por exemplo `v1.2.0`, para um commit já validado.
- No GitHub, execute **Actions → Mobile Android → Run workflow**, informando a tag.

O job valida a URL, constrói e baixa o APK assinado via EAS, gera o checksum SHA-256
e cria um **rascunho** de GitHub Release com ambos os arquivos. Revise o artefato,
teste-o contra o backend real e publique o Release manualmente. O workflow não cria
releases automaticamente em todo push/tag e nunca publica na Play Store.

## Instalação e atualização manual

1. Baixe `sa-education.apk` e `sa-education.apk.sha256` do GitHub Release oficial.
2. Verifique o checksum SHA-256 com uma ferramenta confiável antes de instalar.
3. Abra o APK no Android. O sistema pode solicitar autorização para instalar apps
   desta fonte; permita somente para o navegador/gerenciador usado para baixar o
   arquivo e desative a autorização depois. Leia os avisos do Play Protect; não
   desative as proteções para contorná-los.
4. Confirme que o app consegue alcançar o backend e faça login com uma conta de
   teste autorizada antes da divulgação ampla.

Não há atualização automática configurada. Para distribuir uma nova versão, publique
um APK novo com o mesmo `applicationId` e a mesma chave de assinatura, usando uma
versão/build number superior. O usuário baixa e instala manualmente; se a assinatura
não corresponder, o Android recusará a atualização e poderá exigir desinstalação
(com perda dos dados locais protegidos).

### Resolução de problemas de build e instalação

- **URL ausente/inválida:** configure `EXPO_PUBLIC_API_BASE_URL` no ambiente EAS
  correspondente; production exige HTTPS e domínio público terminando em `/api/v1`.
- **EAS não encontra o projeto:** execute `npx eas-cli init`, confirme o
  `extra.eas.projectId` no `app.config.ts` e autentique `npx eas-cli login`.
- **Build não autorizado no CI:** confira `EAS_TOKEN` e a associação do projeto; o
  workflow não deve receber keystore nem senha de assinatura do Android.
- **APK não instala como atualização:** confirme `com.saeducation.mobile`, a chave
  de assinatura original e o `versionCode` incrementado.
- **App não conecta ao servidor:** confirme DNS, TLS/certificado HTTPS, endpoint
  `/api/v1`, disponibilidade da API e encaminhamento do cabeçalho `Authorization`.
  HTTP para hosts locais é exclusivo de desenvolvimento, nunca de distribuição.
- **Instalação bloqueada pelo Android:** valide a origem e o checksum do APK e siga
  apenas a autorização de instalação da fonte confiável. Não desative Play Protect.
