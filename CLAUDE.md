# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a **RAG (Retrieval Augmented Generation) AI Documentation Chatbot** built with Symfony 7.1 and PHP 8.2+. The application crawls documentation websites, stores content with vector embeddings in PostgreSQL using pgvector, and provides an interactive chat interface powered by OpenAI's GPT-4o for answering documentation questions.

## Key Technologies

- **Backend**: Symfony 7.1, Doctrine ORM with PostgreSQL + pgvector extension
- **Vector Search**: OpenAI text-embedding-3-small (1536 dimensions), cosine similarity
- **AI**: OpenAI GPT-4o for chat completions
- **Frontend**: Symfony UX Live Components, Twig, Stimulus
- **Web Crawling**: Spatie Crawler package

## Common Commands

### Development Server
```bash
# Start Symfony development server
symfony serve

# Or using PHP built-in server
php -S localhost:8000 -t public/
```

### Database Operations
```bash
# Run migrations (creates document and message tables, enables pgvector extension)
php bin/console doctrine:migrations:migrate

# Create a new migration
php bin/console make:migration
```

### Crawling & Data Management
```bash
# Crawl documentation website and populate embeddings
# URL is configured via DOCUMENTATION_URL env var
php bin/console app:crawl
```

### Asset Management
```bash
# Install/compile frontend assets
php bin/console importmap:install
php bin/console asset-map:compile

# Clear cache
php bin/console cache:clear
```

## Architecture Overview

### RAG Pipeline Flow

1. **Indexing Phase** (app:crawl command):
   - Crawls website starting from `DOCUMENTATION_URL`
   - Extracts content split by H1-H6 sections (DocumentExtractor)
   - Generates OpenAI embeddings for each section
   - Stores documents with vector embeddings in PostgreSQL

2. **Query Phase** (Chat component):
   - User submits question via Live Component
   - Question converted to embeddings using OpenAI API
   - Performs vector similarity search to find top 5 most relevant documents
   - Passes context + chat history to GPT-4o for answer generation
   - Stores conversation in message history

### Core Components

**Crawling System** (src/Crawl/):
- `Crawler`: Orchestrates Spatie Crawler to crawl internal URLs
- `Observer`: CrawlObserver implementation that processes each crawled page
- `DocumentExtractor`: Splits HTML content by heading tags (H1-H6), extracts title + content, attaches anchor URLs

**AI Integration** (src/OpenAI/Client.php):
- `getEmbeddings()`: Generates 1536-dim vectors via text-embedding-3-small, cached by content hash
- `getAnswer()`: Sends relevant docs + chat history to GPT-4o with system prompt instructing concise, technically credible responses with source URLs

**Entities**:
- `Document`: Stores URL, title, content, embeddings (vector[1536]), tokens count
- `Message`: Chat history with content, is_me flag, created_at timestamp

**Repositories**:
- `DocumentRepository::findNearest()`: Uses pgvector's `cosine_similarity()` DQL function to find 5 most similar documents
- `MessageRepository::findLatest()`: Retrieves recent chat history

**Live Component** (src/Twig/Components/Chat.php):
- Symfony UX Live Component handling real-time chat interface
- submit() action: processes user message, retrieves relevant docs, gets AI answer, persists both messages

### Database Schema

**PostgreSQL with pgvector extension** (requires PostgreSQL 11+):
- `document` table: id (UUID), url (TEXT), title (TEXT), content (TEXT), embeddings (vector[1536]), tokens (INT)
- `message` table: id (VARCHAR), content (TEXT), is_me (BOOLEAN), created_at (TIMESTAMP)

Vector type registered in Doctrine as custom DQL type with distance/similarity functions.

## Configuration

### Environment Variables (.env)
Required variables:
- `DATABASE_URL`: PostgreSQL connection with pgvector support
- `OPENAI_API_KEY`: OpenAI API key for embeddings and completions
- `DOCUMENTATION_URL`: Base URL to crawl (e.g., https://castor.jolicode.com)
- `APP_SECRET`: Symfony secret for sessions/CSRF

### Doctrine Configuration
- Custom vector type registered: `Partitech\DoctrinePgVector\Type\VectorType`
- Custom DQL functions: `cosine_similarity()`, `distance()`, `inner_product()`

## Development Notes

### Working with Embeddings
- Embeddings are 1536-dimensional floats from OpenAI's text-embedding-3-small model
- Vector similarity uses cosine similarity for retrieval (configured in Doctrine DQL)
- Embeddings cached in Symfony cache to avoid redundant API calls

### Adding New Console Commands
Follow Symfony conventions:
```php
#[AsCommand(name: 'app:command-name', description: '...')]
class MyCommand extends Command { }
```

### Modifying Document Extraction
The `DocumentExtractor` splits by heading tags - customize `extract()` method to change chunking strategy. Each chunk includes:
- URL with anchor if heading has ID attribute
- Heading text as title
- Content until next heading (cleaned, preserving <code> tags)

### Chat System Prompt
Located in `OpenAI\Client::getAnswer()` - modify to change AI behavior. Current prompt emphasizes:
- Concise, technically credible responses
- Only use provided information
- Include source document URLs

### Vector Search Tuning
- Adjust `setMaxResults(5)` in `DocumentRepository::findNearest()` to change number of context documents
- Modify `cosine_similarity` ordering or add filtering criteria for relevance tuning

## Important Constraints

- Requires PostgreSQL with pgvector extension installed
- OpenAI API key must have access to text-embedding-3-small and gpt-4o models
- Crawling respects internal URLs only (configured in CrawlInternalUrls profile)
- Document vectors are fixed at 1536 dimensions (changing models requires migration)