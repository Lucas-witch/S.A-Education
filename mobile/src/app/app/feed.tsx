import { UnavailableScreen } from '../../features/learning/UnavailableScreen';

export default function FeedRoute() {
  return (
    <UnavailableScreen
      explanation="O backend oferece ações e comentários do Feed, mas ainda não possui uma operação mobile para listar publicações. As interações não podem ser usadas sem essa listagem."
      subtitle="Publicações e conversas da comunidade."
      title="Feed"
    />
  );
}
