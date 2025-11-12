<?php

namespace App\OpenAI;

use App\Entity\Message;
use App\Entity\Document;
use App\Helper\TextHelper;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class Client
{
    public function __construct(
        #[Autowire('%env(OPENAI_API_KEY)%')]
        private readonly string $apiKey,
        private readonly HttpClientInterface $client,
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * @return array<float>
     * @throws InvalidArgumentException
     */
    public function getEmbeddings(string $content): array
    {
        // Validate content is not empty
        $content = trim($content);
        if (empty($content)) {
            throw new \InvalidArgumentException('Content cannot be empty for embeddings generation.');
        }

        $content = TextHelper::truncateContent($content);
        $cacheKey = md5($content);

        $cacheItem = $this->cache->getItem($cacheKey);

        if (!$cacheItem->isHit()) {
            $data = $this->call('/v1/embeddings', [
                'model' => 'text-embedding-3-small', // "small" model will produce vectors of 1536 dimensions
                'input' => $content,
            ]);

            $cacheItem->set($data);
            $this->cache->save($cacheItem);
        }

        $data = $cacheItem->get();

        if (!($data['data'][0]['embedding'] ?? false)) {
            throw new \RuntimeException('Could not get embeddings from OpenAI response.');
        }

        return $data['data'][0]['embedding'];
    }

    /**
     * @param Document[] $documents
     * @param Message[] $historyMessages
     */
    public function getAnswer(array $documents, array $historyMessages): string
    {
        $prompt = 'You are a friendly chatbot. \
    You respond in a concise, technically credible tone (but do not hesitate to add examples if needed). \
    You only use information from the provided information. \
    Please add the link of the relevant documents to the end of your response (do not invent url, only use the one we provided).';

        $messages = [
            [
                'role' => 'system',
                'content' => $prompt,
            ],
        ];

        $relevantInformation = 'Relevant information: \n';
        foreach ($documents as $document) {
            $relevantInformation .= json_encode([
                    'title' => $document->title,
                    'content' => $document->content,
                    'url' => $document->url,
                ]) . "\n";
        }

        $messages[] = [
            'role' => 'system',
            'content' => $relevantInformation,
        ];

        foreach ($historyMessages as $message) {
            $messages[] = [
                'role' => $message->isMe ? 'user' : 'assistant',
                'content' => $message->content,
            ];
        }

        $data = $this->call('/v1/chat/completions', [
            'model' => 'gpt-4o',
            'messages' => $messages,
        ]);

        if (!($data['choices'][0]['message']['content'] ?? false)) {
            throw new \RuntimeException('Could not get suggestion from OpenAI response.');
        }

        return $data['choices'][0]['message']['content'];
    }

    /**
     * Generate a short, concise title for a conversation based on the first message
     */
    public function generateConversationTitle(string $firstMessage): string
    {
        $messages = [
            [
                'role' => 'system',
                'content' => 'Generate a very short, concise title (max 5 words) for a conversation that starts with the following message. Only respond with the title, nothing else.',
            ],
            [
                'role' => 'user',
                'content' => $firstMessage,
            ],
        ];

        $data = $this->call('/v1/chat/completions', [
            'model' => 'gpt-4o-mini',
            'messages' => $messages,
            'max_tokens' => 20,
        ]);

        if (!($data['choices'][0]['message']['content'] ?? false)) {
            // Fallback: use first 50 chars of the message
            return mb_substr($firstMessage, 0, 50) . (mb_strlen($firstMessage) > 50 ? '...' : '');
        }

        return trim($data['choices'][0]['message']['content']);
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<mixed>
     */
    private function call(string $endpoint, array $data): array
    {
        try {
            $response = $this->client->request('POST', "https://api.openai.com{$endpoint}", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiKey}",
                ],
                'json' => $data,
            ]);
        } catch (TransportExceptionInterface $e) {
            dd($e);
        }

        return $response->toArray();
    }
}
