OAuth placeholders

Esta pasta contém arquivos de referência para integrar OAuth (Google / Microsoft).

Para ativar OAuth:
1. Registre seu app no Google Console / Azure Portal.
2. Defina os callbacks para: `https://your-domain.com/oauth/google_callback.php` (substitua domínio).
3. Preencha `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `MICROSOFT_CLIENT_ID`, `MICROSOFT_CLIENT_SECRET` em `.env`.
4. Implemente o fluxo usando as bibliotecas oficiais ou endpoints OAuth2.

Arquivos de exemplo (não implementam fluxo completo):
- `google_redirect.php` — redireciona para a tela de autorização.
- `google_callback.php` — ponto de entrada para processar o callback.

Por segurança, não coloque credenciais diretamente no repositório.
