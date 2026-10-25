import { UnavailableScreen } from '../../features/learning/UnavailableScreen';

export default function QuestionsRoute() {
  return (
    <UnavailableScreen
      explanation="A API mobile ainda não disponibiliza catálogo de questões, execução de quizzes, registro de tentativas ou correção de respostas."
      subtitle="Pratique e acompanhe sua aprendizagem."
      title="Questões"
    />
  );
}
