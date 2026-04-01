Kun # Sem Sets Architecture

## Overview

Sem Sets is a **Symfony 6.4 web application** for creating, editing, and publishing musical composition sets with:
- MIDI-based content management
- Dynamic grid-based sequencing
- Effect parameter mapping
- Full version control with snapshots
- JSON payload export for external systems

The application uses a **layered architecture** with separation of concerns across Controllers, Services, Entities, and Repositories.

---

## Technology Stack

| Component | Technology | Version | Purpose |
|-----------|-----------|---------|---------|
| **Framework** | Symfony | 6.4 | Web application framework |
| **Language** | PHP | 8.1+ | Backend runtime |
| **Database** | MariaDB | 10.11 | Data persistence |
| **ORM** | Doctrine | 2.x | Object-relational mapping |
| **Templating** | Twig | 3.x | Server-side HTML rendering |
| **Forms** | Symfony Forms | 6.4 | Data binding & validation |
| **File Storage** | Flysystem | 3.x | Abstract filesystem layer |
| **MIDI Processing** | php-midi | - | MIDI file parsing & analysis |
| **API Response** | JSON | - | External system integration |
| **Authentication** | Symfony Security | 6.4 | PIN-code based auth |
| **Asset Pipeline** | AssetMapper | - | CSS/JS bundling |

---

## Layered Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    USER INTERFACE LAYER                     │
│              (Twig Templates + HTML Forms)                  │
└──────────────────────┬──────────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────────┐
│                  CONTROLLER LAYER                           │
│  (HTTP Request Handling, Form Processing, Response Mgmt)   │
│  Files: src/Controller/*.php                               │
└──────────────────────┬──────────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────────┐
│                   SERVICE LAYER                             │
│   (Business Logic, Workflows, Data Transformations)         │
│   Files: src/Service/*.php                                 │
│   Key Services:                                             │
│   - DocumentPayloadBuilder (JSON export)                   │
│   - DocumentSnapshotService (versioning)                   │
│   - VersioningService (version history)                    │
│   - DocumentExportService (ZIP/JSON export)                │
│   - AssetStorage (file upload handling)                    │
│   - MidiAnalyzer (MIDI analysis)                           │
└──────────────────────┬──────────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────────┐
│                ENTITY & REPOSITORY LAYER                    │
│   (Data Models, Database Access)                            │
│   Files: src/Entity/*.php, src/Repository/*.php            │
│   - Entities define DB structure via Doctrine              │
│   - Repositories handle queries                            │
└──────────────────────┬──────────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────────┐
│                   DATABASE LAYER                            │
│              (MariaDB 10.11)                                │
│        Managed via Doctrine migrations                      │
└──────────────────────────────────────────────────────────────┘
```

---

## Directory Structure

```
src/
├── Command/                    # CLI commands for admin tasks
│   └── CreateUserCommand.php   # Create user accounts
│
├── Controller/                 # HTTP request handlers
│   ├── DocumentController.php       # CRUD: documents, editing, export
│   ├── ApiController.php            # Public JSON API endpoints
│   ├── SecurityController.php       # Login/logout
│   ├── EffectSettingsController.php # CRUD: effect configurations
│   ├── SemSymAuthController.php     # API authentication
│   ├── DocumentAssetSplitTracksController.php     # MIDI track splitting
│   └── DocumentAssetStaggerNotesController.php    # MIDI note manipulation
│
├── Entity/                     # Doctrine entity models
│   ├── User.php                    # Users & API keys
│   ├── Document.php                # Main composition set
│   ├── DocumentTrack.php           # Individual track
│   ├── DocumentVersion.php         # Version history snapshot
│   ├── Asset.php                   # Uploaded MIDI files
│   ├── InstrumentPart.php          # Grid-to-effect mapping
│   ├── DocumentTrackEffect.php     # Effect assignment
│   ├── EffectSettings.php          # Effect configurations
│   └── PayloadBlock.php            # Template structures
│
├── Repository/                 # Data access layer
│   ├── DocumentRepository.php
│   ├── DocumentVersionRepository.php
│   ├── AssetRepository.php
│   └── [other repositories...]
│
├── Form/                       # Symfony form types
│   ├── DocumentFormType.php         # Main document editor
│   ├── DocumentTrackType.php        # Track configuration
│   ├── InstrumentPartType.php       # Parameter mapping
│   ├── EffectSettingsType.php       # Effect editor
│   └── [other form types...]
│
├── Service/                    # Business logic layer
│   ├── DocumentPayloadBuilder.php   # Builds JSON payloads
│   ├── DocumentSnapshotService.php  # Creates version snapshots
│   ├── VersioningService.php        # Version management
│   ├── DocumentExportService.php    # ZIP/JSON export
│   ├── AssetStorage.php             # File upload handling
│   ├── EffectConfigMerger.php       # Merges effect configs
│   └── [other services...]
│
├── Midi/                       # MIDI file processing
│   ├── MidiAnalyzer.php            # Analyzes MIDI structure
│   ├── MidiTrackSplitter.php       # Splits multi-track MIDI
│   ├── MidiNoteStaggerer.php       # Manipulates timing/velocity
│   ├── PhpMidiFile.php             # Wrapper for php-midi
│   ├── MidiFileInterface.php       # Interface definition
│   └── Dto/MidiSummary.php         # Analysis result DTO
│
├── EventSubscriber/            # Event listeners
│   └── [event subscribers...]
│
├── Security/                   # Authentication
│   └── PinCodeAuthenticator.php    # PIN-code auth
│
├── Enum/                       # Type definitions
│   └── [enums...]
│
├── DataFixtures/               # Test data
│   └── [fixture loaders...]
│
└── Kernel.php                  # Application kernel
```

---

## Core Design Patterns

### 1. **Dependency Injection**

All services use **constructor-based dependency injection**:

```php
class DocumentController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DocumentPayloadBuilder $payloadBuilder,
        private readonly DocumentExportService $exportService,
    ) {}
}
```

**Benefits:**
- Loose coupling between components
- Easy to test with mock dependencies
- Type-safe service resolution

---

### 2. **Repository Pattern**

Data access is abstracted through repositories:

```php
// Get documents from database
$documents = $documentRepository->findBy(['published' => true]);

// Custom queries in Repository classes
public function findPublishedByUser(User $user): array
{
    return $this->createQueryBuilder('d')
        ->where('d.published = true')
        ->andWhere('d.createdBy = :user')
        ->setParameter('user', $user)
        ->getQuery()
        ->getResult();
}
```

**Benefits:**
- Queries centralized in one place
- Easy to optimize or refactor
- Clear API for data access

---

### 3. **Service Layer for Business Logic**

Complex operations are extracted into dedicated services:

```php
// Don't do business logic in Controller
// $controller->doSomethingComplex(); ❌

// Use Service
$payload = $payloadBuilder->buildPayloadJson($document);
$version = $versioningService->saveVersion($document, $changelog);
```

**Benefits:**
- Reusable across controllers/commands
- Easier to test in isolation
- Clear separation of concerns

---

### 4. **Doctrine Entities as Domain Models**

Entities represent domain concepts and are mapped to database tables:

```php
#[ORM\Entity(repositoryClass: DocumentRepository::class)]
class Document
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private string $slug;

    #[ORM\Column(type: 'json')]
    private array $levelDurations = [];

    // Relationships
    #[ORM\OneToMany(targetEntity: DocumentTrack::class, mappedBy: 'document')]
    private Collection $tracks;
}
```

**Benefits:**
- Single source of truth for data structure
- Database constraints defined in code
- Type-safe entity manipulation

---

### 5. **Form Types for Data Binding**

Symfony Forms handle data binding and validation:

```php
// Form automatically maps request data to Entity
$form = $this->createForm(DocumentFormType::class, $document);
$form->handleRequest($request);

if ($form->isSubmitted() && $form->isValid()) {
    // $document is now updated with form data
    $this->em->persist($document);
    $this->em->flush();
}
```

**Benefits:**
- Automatic request-to-entity binding
- Built-in validation framework
- CSRF protection by default

---

## Key Workflows

### 1. **Request → Response Flow**

```
HTTP Request
    ↓
Router matches URL to Controller action
    ↓
Controller dependency injection resolves services
    ↓
Controller handles form or query data
    ↓
Service layer performs business logic
    ↓
Repository queries/persists data
    ↓
Entity Manager flushes to database
    ↓
Controller renders response (Twig template)
    ↓
HTTP Response
```

### 2. **Document Creation Flow**

```
User submits form
    ↓
DocumentController::new() receives request
    ↓
Form type: NewDocumentFormType binds data
    ↓
Validation passes
    ↓
Create Document entity with defaults (grid 2x2, BPM, etc.)
    ↓
User is set via $this->getUser()
    ↓
Entity Manager persists
    ↓
Database transaction commits
    ↓
Redirect to document editor
```

### 3. **Document Export (Payload) Flow**

```
User clicks "Export"
    ↓
DocumentController::export() action
    ↓
DocumentPayloadBuilder::buildPayloadJson(Document)
    ↓
    ├─ Get BPM, levelDurations, timeSignature
    ├─ Get PayloadBlock defaults (template)
    ├─ Iterate DocumentTracks
    │   ├─ Get loop lengths (bars → beats)
    │   ├─ Get InstrumentParts (grid mappings)
    │   ├─ Get EffectSettings via EffectConfigMerger
    │   └─ Build instruments config
    └─ Merge everything into final JSON
    ↓
Return JSON response / save to ZIP
```

### 4. **Versioning Flow**

```
User edits document & clicks Save
    ↓
DocumentSnapshotService::createSnapshot()
    ↓
    ├─ Serialize entire document structure to JSON
    ├─ Create DocumentVersion record
    │   (version number, JSON payload, author, timestamp)
    └─ Store in var/uploads/sets/{doc_id}/versions/{n}/document.json
    ↓
Update Document.headVersion reference
    ↓
User can now see version history and rollback
```

---

## Data Flow Diagram

```
MIDI File Upload → AssetStorage → Asset Entity → DocumentTrack
                                                        ↓
                              InstrumentPart (Grid Mapping)
                                  ↓
                          EffectSettings → JSON Payload
                                  ↓
                    DocumentPayloadBuilder (export)
```

---

## Configuration & Environment

### Symfony Config Structure

```
config/
├── packages/
│   ├── doctrine.yaml          # Database configuration
│   ├── framework.yaml         # Core framework settings
│   ├── security.yaml          # Authentication/authorization
│   ├── twig.yaml              # Template engine
│   └── flysystem.yaml         # File storage configuration
├── routes/                    # Route definitions
└── services.yaml              # Service container definitions
```

### File Storage (Flysystem)

Two storage contexts:
- **`default.storage`** → `/var/storage/default/` (general app data)
- **`uploads.storage`** → `/var/uploads/` (MIDI files, exports, versions)

Version structure:
```
var/uploads/sets/
├── {document_id}/
│   ├── versions/
│   │   ├── 1/document.json
│   │   ├── 2/document.json
│   │   └── {n}/document.json
│   ├── latest/
│   │   └── document.json
│   └── assets/
│       └── {asset_id}.mid
```

---

## Security

### Authentication
- **Type**: PIN-code based (email + 6-digit PIN)
- **Implementation**: `src/Security/PinCodeAuthenticator.php`
- **Session Management**: Symfony session (stored in database or file)

### Authorization
- **Granularity**: Per-user document ownership
- **API Keys**: Users can generate API keys for `sem-sym` integration

### HTTPS & CSRF
- All form submissions protected by Symfony CSRF tokens
- HTTPS recommended for production

---

## Key Entities & Relationships

```
User (1) ───────┐
                │
                ├─── (creates) ──→ Document (1) ──→ DocumentVersion (*)
                │                       │
                └─── (owns) ────────────┘
                                        │
                                        └─→ DocumentTrack (*)
                                            │
                                            ├─→ Asset (MIDI files)
                                            ├─→ InstrumentPart (*)
                                            │   └─→ (maps to grid)
                                            └─→ DocumentTrackEffect (*)
                                                └─→ EffectSettings
```

---

## Common Operations

### Get a Document with All Data
```php
$document = $documentRepository->find($id);
// Doctrine lazy-loads related entities on access:
$tracks = $document->getTracks(); // loads DocumentTrack collection
$versions = $document->getVersions(); // loads versions
```

### Create a New Document
```php
$document = new Document();
$document->setTitle('My Set');
$document->setSlug('my-set');
$document->setGridColumns(2);
$document->setGridRows(2);
$document->setLevelDurations([32, 32]);
$document->setCreatedBy($user);

$em->persist($document);
$em->flush();
```

### Export Document as JSON
```php
$payload = $payloadBuilder->buildPayloadJson($document);
// Returns: JSON string ready for external system
```

### Create a Version Snapshot
```php
$versioningService->saveVersion(
    $document,
    'Updated BPM and added track'
);
// Creates DocumentVersion with full JSON snapshot
```

---

## Testing Strategy

- **Unit Tests**: Service logic in isolation
- **Integration Tests**: Controller → Service → Database
- **Form Tests**: Form data binding and validation
- **Repository Tests**: Custom query methods

Location: `tests/` directory

---

## Performance Considerations

1. **Lazy Loading**: Doctrine relations are lazy-loaded by default
2. **Eager Loading**: Use `JOIN` in Repository queries for related data
3. **Caching**: Implement for MIDI analysis (expensive operation)
4. **Database Indexes**: Critical paths should be indexed
5. **Query Optimization**: Use `SELECT DISTINCT` only when necessary

---

## Extension Points

### Adding a New Feature

1. **Define Entity** in `src/Entity/`
2. **Create Repository** in `src/Repository/`
3. **Add Form Type** in `src/Form/` (if user input)
4. **Create Service** in `src/Service/` (business logic)
5. **Create/Update Controller** in `src/Controller/`
6. **Create Migration** (auto-generates for schema changes)
7. **Create Twig Template** in `templates/`

### Adding a New API Endpoint

1. Add method to `src/Controller/ApiController.php`
2. Add `#[Route(...)]` attribute with path
3. Return JSON response:
   ```php
   return $this->json(['key' => $value]);
   ```
4. Add tests in `tests/Controller/`

---

## Debugging & Troubleshooting

### View Database Queries
```bash
# Enable Symfony profiler in dev mode
# Visit /_profiler in your browser
```

### Check Entity Mapping
```bash
docker compose run --rm app php bin/console doctrine:mapping:info
```

### Validate Schema
```bash
docker compose run --rm app php bin/console doctrine:schema:validate
```

### List Routes
```bash
docker compose run --rm app php bin/console debug:router
```

---

## Next Steps

For more detailed information, see:
- **[DATABASE_SCHEMA.md](DATABASE_SCHEMA.md)** — Entity structure & relationships
- **[QUICKSTART.md](QUICKSTART.md)** — Development setup & first steps
- **[components/controllers.md](components/controllers.md)** — HTTP endpoints
- **[components/services.md](components/services.md)** — Business logic layer
