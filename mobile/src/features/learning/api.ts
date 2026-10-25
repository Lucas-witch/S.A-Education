export interface StudyRoom {
  id: number;
  nome: string;
  materia: string;
  descricao: string;
  privacidade: string;
  alunos: number;
  categoria: string;
  area_conhecimento: string;
  prof: string;
}

export interface CatalogItem {
  id: number;
  source: string;
  type: string;
  typeLabel: string;
  title: string;
  description: string;
  responsible: string;
  category: string;
  subject: string;
  accessLabel: string;
  premiumOnly: boolean;
}

export interface CatalogPage {
  items: CatalogItem[];
  page: number;
  limit: number;
  total: number;
  totalPages: number;
}

export interface RankingEntry {
  nome_publico: string;
  pontos: number;
  posicao: number;
}

export type AuthenticatedRequest = <T>(
  path: string,
  options?: { method?: 'GET' | 'POST'; body?: unknown },
) => Promise<{ data: T; meta: Record<string, unknown> | null }>;

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function text(value: unknown): string {
  return typeof value === 'string' || typeof value === 'number' ? String(value) : '';
}

function positiveInteger(value: unknown): number {
  const parsed = typeof value === 'number' ? value : Number(value);
  return Number.isSafeInteger(parsed) && parsed >= 0 ? parsed : 0;
}

export function parseRooms(value: unknown): StudyRoom[] {
  if (!Array.isArray(value)) {
    throw new Error('O servidor retornou uma lista de salas inválida.');
  }

  return value.map((item) => {
    if (
      !isRecord(item) ||
      positiveInteger(item.id) < 1 ||
      typeof item.nome !== 'string'
    ) {
      throw new Error('O servidor retornou uma sala em formato inválido.');
    }
    return {
      id: positiveInteger(item.id),
      nome: item.nome,
      materia: text(item.materia),
      descricao: text(item.descricao),
      privacidade: text(item.privacidade),
      alunos: positiveInteger(item.alunos),
      categoria: text(item.categoria),
      area_conhecimento: text(item.area_conhecimento),
      prof: text(item.prof),
    };
  });
}

export function parseCatalogPage(value: unknown, requestedPage: number): CatalogPage {
  if (!isRecord(value) || !Array.isArray(value.items) || !isRecord(value.meta)) {
    throw new Error('O servidor retornou um catálogo em formato inválido.');
  }
  const page = positiveInteger(value.meta.page);
  const limit = positiveInteger(value.meta.limit);
  const total = positiveInteger(value.meta.total);
  const totalPages = positiveInteger(value.meta.totalPages);
  if (page < 1 || limit < 1) {
    throw new Error('O servidor retornou metadados de paginação inválidos.');
  }

  const items = value.items.map((item) => {
    if (
      !isRecord(item) ||
      positiveInteger(item.id) < 1 ||
      typeof item.title !== 'string' ||
      typeof item.source !== 'string'
    ) {
      throw new Error('O servidor retornou um conteúdo em formato inválido.');
    }
    return {
      id: positiveInteger(item.id),
      source: item.source,
      type: text(item.type),
      typeLabel: text(item.typeLabel),
      title: item.title,
      description: text(item.description),
      responsible: text(item.responsible),
      category: text(item.category),
      subject: text(item.subject),
      accessLabel: text(item.accessLabel),
      premiumOnly: item.premiumOnly === true,
    };
  });

  return { items, page: page || requestedPage, limit, total, totalPages };
}

export function parseRanking(value: unknown): RankingEntry[] {
  if (!Array.isArray(value)) {
    throw new Error('O servidor retornou um ranking inválido.');
  }

  return value.map((item) => {
    if (!isRecord(item) || typeof item.nome_publico !== 'string') {
      throw new Error('O servidor retornou uma posição do ranking inválida.');
    }
    return {
      nome_publico: item.nome_publico,
      pontos: positiveInteger(item.pontos),
      posicao: positiveInteger(item.posicao),
    };
  });
}

export function catalogSearchPath(query: string, page: number, type: string): string {
  const params = new URLSearchParams({
    resource: 'pesquisa',
    q: query.trim(),
    page: String(page),
    limit: '20',
    tipo: type,
    origem: 'biblioteca',
  });
  return `index.php?${params.toString()}`;
}

export function roomsSearchPath(query: string, page: number, limit = 20): string {
  const params = new URLSearchParams({
    resource: 'salas',
    q: query.trim(),
    page: String(page),
    limit: String(limit),
  });
  return `index.php?${params.toString()}`;
}
