<?php

namespace App\Entity;

use App\Repository\BottinRepository;
use App\Repository\DocumentRepository;
use App\Repository\PivotRepository;
use Doctrine\ORM\Mapping as ORM;
use Knp\DoctrineBehaviors\Contract\Entity\TimestampableInterface;
use Knp\DoctrineBehaviors\Model\Timestampable\TimestampableTrait;

#[ORM\Entity(repositoryClass: DocumentRepository::class)]
class Document implements TimestampableInterface
{
    use TimestampableTrait;

    public const VECTOR_LENGTH_SHORT = 1536;
    public const VECTOR_LENGTH_LONG = 1536;

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    #[ORM\Column(type: 'guid', nullable: false)]
    public readonly string $id;

    #[ORM\Column(type: 'integer')]
    public int $tokens;

    /** @var float[] */
    #[ORM\Column(type: 'vector', length: self::VECTOR_LENGTH_SHORT)]
    public array $embeddings;

    public function __construct(
        #[ORM\Column(type: 'text')]
        public readonly string $url,
        #[ORM\Column(type: 'text')]
        public readonly string $title,
        #[ORM\Column(type: 'text')]
        public readonly string $siteName,
        #[ORM\Column(type: 'text')]
        public readonly string $typeOf,
        #[ORM\Column(type: 'text')]
        public readonly string $content,
        #[ORM\Column(type: 'text', nullable: true)]
        public readonly string $referenceId
    ) {
        $this->id = \uuid_create();
    }

    /**
     * @param float[] $embeddings
     */
    public function setEmbeddings(array $embeddings): void
    {
        $this->tokens = \count($embeddings);
        $this->embeddings = $embeddings;
    }

    public static function createFromPost(\stdClass $post, string $siteName): Document
    {
        $content = strip_tags($post->content->rendered);
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);

        if (count($post->categories) > 0) {
            $content .= 'TAGS: ';
        }
        foreach ($post->categories as $category) {
            $content .= ' '.$category->name;
        }

        $referenceId = self::createReferenceId($post->id, 'post', $siteName);

        return new Document($post->link, $post->title->rendered, $siteName, "post", $content, $referenceId);
    }

    public static function createFromAttachment(\stdClass $post, string $siteName): Document
    {
        $content = ' ';
        $referenceId = self::createReferenceId($post->id, 'attachment', $siteName);

        return new Document($post->link, $post->title->rendered, $siteName, "attachment", $content, $referenceId);
    }

    public static function createFromFiche(\stdClass $fiche): Document
    {
        $content = BottinRepository::getContentFiche($fiche);
        $link = "https://bottin.marche.be/fiche/{$fiche->slug}";
        if (count($fiche->classements) > 0) {
            $content .= 'TAGS: ';
        }
        foreach ($fiche->classements as $category) {
            $content .= ' '.$category->name.' '.$category->description;
        }
        $referenceId = self::createReferenceId($fiche->id, 'fiche');

        return new Document($link, $fiche->societe, 'bottin', "societe", $content, $referenceId);
    }

    public static function createFromEvent(\stdClass $event): Document
    {
        $content = PivotRepository::getContentEvent($event);
        $link = "https://marche.local/tourisme/agenda-des-manifestations/manifestation/{$event->codeCgt}";

        $referenceId = self::createReferenceId($event->codeCgt, 'event');

        return new Document($link, $event->nom, 'event', "event", $content, $referenceId);
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'url' => $this->url,
            'id' => $this->id,
            'referenceId' => $this->referenceId,
            'content' => $this->content,
            'siteName' => $this->siteName,
        ];
    }

    public static function createReferenceId(string $ID, string $string, ?string $extra = null): string
    {
        $id = $ID.'-'.$string.'-'.$extra;
        if ($extra === null) {
            return $id;
        }

        return $id.'-'.$extra;
    }

}

