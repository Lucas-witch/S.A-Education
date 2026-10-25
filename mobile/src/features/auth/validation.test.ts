import { validateLoginInput } from './validation';

describe('validateLoginInput', () => {
  it('requires an identifier and password', () => {
    expect(validateLoginInput('   ', 'senha')).toBe('Informe seu usuário ou e-mail.');
    expect(validateLoginInput('aluno', '')).toBe('Informe sua senha.');
  });

  it('accepts either username or email as the identifier', () => {
    expect(validateLoginInput(' aluno ', 'senha')).toBeNull();
    expect(validateLoginInput('aluno@example.invalid', 'senha')).toBeNull();
  });

  it('enforces the input limits documented by the backend', () => {
    expect(validateLoginInput('a'.repeat(256), 'senha')).toBe(
      'O usuário ou e-mail excede o limite permitido.',
    );
    expect(validateLoginInput('aluno', 'a'.repeat(4097))).toBe(
      'A senha excede o limite permitido.',
    );
  });
});
