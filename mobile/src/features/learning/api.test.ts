import {
  catalogSearchPath,
  parseCatalogPage,
  parseRanking,
  parseRooms,
  roomsSearchPath,
} from './api';

describe('learning API contracts', () => {
  it('parses paginated room results from the documented rooms resource', () => {
    expect(
      parseRooms([
        {
          id: '12',
          nome: 'Sala de Biologia',
          materia: 'Biologia',
          descricao: 'Grupo de estudo',
          privacidade: 'publica',
          alunos: '8',
        },
      ]),
    ).toEqual([
      {
        id: 12,
        nome: 'Sala de Biologia',
        materia: 'Biologia',
        descricao: 'Grupo de estudo',
        privacidade: 'publica',
        alunos: 8,
        categoria: '',
        area_conhecimento: '',
        prof: '',
      },
    ]);
  });

  it('rejects malformed room responses', () => {
    expect(() => parseRooms({ items: [] })).toThrow('lista de salas inválida');
    expect(() => parseRooms([{ id: 0, nome: 'Sala' }])).toThrow(
      'sala em formato inválido',
    );
  });

  it('parses catalog result fields and paging metadata', () => {
    expect(
      parseCatalogPage(
        {
          items: [
            {
              id: 4,
              source: 'biblioteca',
              type: 'livro',
              typeLabel: 'Livro',
              title: 'Aprendizagem',
              description: '',
              responsible: 'Autor',
              category: 'Educação',
              subject: 'Educação',
              accessLabel: 'Acesso público',
              premiumOnly: false,
            },
          ],
          meta: { page: 2, limit: 20, total: 21, totalPages: 2 },
        },
        1,
      ),
    ).toEqual({
      items: [
        {
          id: 4,
          source: 'biblioteca',
          type: 'livro',
          typeLabel: 'Livro',
          title: 'Aprendizagem',
          description: '',
          responsible: 'Autor',
          category: 'Educação',
          subject: 'Educação',
          accessLabel: 'Acesso público',
          premiumOnly: false,
        },
      ],
      page: 2,
      limit: 20,
      total: 21,
      totalPages: 2,
    });
  });

  it('rejects catalog and ranking responses outside the real API contract', () => {
    expect(() => parseCatalogPage([], 1)).toThrow('catálogo em formato inválido');
    expect(() => parseRanking({ data: [] })).toThrow('ranking inválido');
  });

  it('builds encoded v1 search URLs with bounded page size', () => {
    expect(catalogSearchPath('física & química', 2, 'livro')).toContain(
      'resource=pesquisa',
    );
    expect(catalogSearchPath('', 1, '')).toContain('origem=biblioteca');
    expect(catalogSearchPath('física & química', 2, 'livro')).toContain(
      'q=f%C3%ADsica+%26+qu%C3%ADmica',
    );
    expect(roomsSearchPath('sala', 3)).toContain('limit=20');
  });

  it('parses the privacy-preserving ranking fields', () => {
    expect(
      parseRanking([{ nome_publico: 'Estudante', pontos: '90', posicao: '1' }]),
    ).toEqual([{ nome_publico: 'Estudante', pontos: 90, posicao: 1 }]);
  });
});
