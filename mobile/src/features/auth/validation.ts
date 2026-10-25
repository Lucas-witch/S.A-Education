export function validateLoginInput(identifier: string, password: string): string | null {
  const normalizedIdentifier = identifier.trim();
  if (!normalizedIdentifier) {
    return 'Informe seu usuário ou e-mail.';
  }
  if (normalizedIdentifier.length > 255) {
    return 'O usuário ou e-mail excede o limite permitido.';
  }
  if (!password) {
    return 'Informe sua senha.';
  }
  if (password.length > 4096) {
    return 'A senha excede o limite permitido.';
  }
  return null;
}
