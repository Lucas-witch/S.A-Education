# Integração OAuth — não disponível

Os arquivos `google_redirect.php` e `google_callback.php` são placeholders que respondem com HTTP 501. Eles não implementam autenticação OAuth e não devem ser apresentados como um método de login funcional.

Para uma implementação futura, será necessário definir o provedor, cadastrar URLs de callback, configurar credenciais somente no ambiente do servidor e implementar validação de `state`, troca de código, validação de identidade e associação segura de contas. O projeto não carrega arquivos `.env` automaticamente.

Até que esses passos sejam concluídos e testados, use os fluxos de autenticação disponíveis na aplicação.
