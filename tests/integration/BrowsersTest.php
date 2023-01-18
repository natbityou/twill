<?php

namespace A17\Twill\Tests\Integration;

use App\Models\Bio;
use App\Models\Book;
use App\Models\Letter;
use App\Models\Writer;
use App\Repositories\BioRepository;
use App\Repositories\BookRepository;
use App\Repositories\LetterRepository;
use App\Repositories\WriterRepository;
use A17\Twill\Models\RelatedItem;

class BrowsersTest extends TestCase
{
    public ?string $example = 'tests-browsers';

    public function setUp(): void
    {
        parent::setUp();

        $this->login();
    }

    public function createWriters()
    {
        $this->assertEquals(0, Writer::count());

        $writers = collect(['Alice', 'Bob', 'Charlie'])->map(function ($name) {
            return app(WriterRepository::class)->create([
                'title' => $name,
                'published' => true,
            ]);
        });

        $this->assertEquals(3, Writer::count());

        return $writers;
    }

    public function createLetter()
    {
        $item = app(LetterRepository::class)->create([
            'title' => 'Lorem ipsum dolor sit amet',
            'published' => true,
        ]);

        $this->assertEquals(1, Letter::count());

        return $item;
    }

    public function createLetterWithWriters($writers)
    {
        $item = $this->createLetter();

        $this->httpRequestAssert("/twill/letters/{$item->id}", 'PUT', [
            'browsers' => [
                'writers' => $writers->map(function ($writer) {
                    return ['id' => $writer->id];
                }),
            ],
        ]);

        return $item;
    }

    public function createBio()
    {
        $item = app(BioRepository::class)->create([
            'title' => 'Lorem ipsum dolor sit amet',
            'published' => true,
        ]);

        $this->assertEquals(1, Bio::count());

        return $item;
    }

    public function createBioWithWriter($writer)
    {
        $item = $this->createBio();

        $this->httpRequestAssert("/twill/bios/{$item->id}", 'PUT', [
            'browsers' => [
                'writer' => [
                    ['id' => $writer->id],
                ],
            ],
        ]);

        return $item;
    }

    public function createWriterWithbios()
    {
        $writers = $this->createWriters();

        $bios = collect([1, 2])->map(function ($i) {
            return app(BioRepository::class)->create([
                'title' => 'Biography ' . $i,
                'published' => true,
            ]);
        });

        $this->httpRequestAssert("/twill/writers/{$writers[0]->id}", 'PUT', [
            'browsers' => [
                'bios' => $bios->map(function ($writer) {
                    return ['id' => $writer->id];
                }),
            ],
        ]);

        $this->assertEquals(2, Bio::count());

        return $writers[0]->refresh();
    }

    public function createBook()
    {
        $item = app(BookRepository::class)->create([
            'title' => 'Lorem ipsum dolor sit amet',
            'published' => true,
        ]);

        $this->assertEquals(1, Book::count());

        return $item;
    }

    public function createBookWithWriters($writers)
    {
        $item = $this->createBook();

        $this->httpRequestAssert("/twill/books/{$item->id}", 'PUT', [
            'browsers' => [
                'writers' => $writers->map(function ($writer) {
                    return [
                        'id' => $writer->id,
                        'endpointType' => '\\App\\Models\\Writer',
                    ];
                }),
            ],
        ]);
        return $item;
    }

    // FIXME — this is needed for the new admin routes to take effect in the next test,
    // because files are copied in `setUp()` after the app is initialized.
    public function testDummy()
    {
        $this->assertTrue(true);
    }

    public function testBrowserBelongsToMany()
    {
        $writers = $this->createWriters();
        $letter = $this->createLetterWithWriters($writers);

        // User can attach writers
        $this->assertEquals(3, Letter::first()->writers->count());
        $this->assertEquals(
            $writers->pluck('id')->sort()->toArray(),
            Letter::first()->writers->pluck('id')->sort()->toArray()
        );
    }

    public function testBrowserBelongsToManyPreview()
    {
        $writers = $this->createWriters();
        $letter = $this->createLetterWithWriters($writers);

        // User can preview
        $this->httpRequestAssert("/twill/letters/preview/{$letter->id}", 'PUT', []);
        $this->assertSee('This is an letter');
    }

    public function testBrowserBelongsToManyPreviewRevisions()
    {
        $writers = $this->createWriters();
        $letter = $this->createLetterWithWriters($writers);

        // User can preview revisions
        $this->httpRequestAssert("/twill/letters/preview/{$letter->id}", 'PUT', [
            'revisionId' => Letter::first()->revisions->last()->id,
        ]);
        $this->assertSee('This is an letter');
    }

    public function testBrowserBelongsToManyRestoreRevisions()
    {
        $writers = $this->createWriters();
        $letter = $this->createLetterWithWriters($writers);

        // User can restore revisions
        $this->httpRequestAssert("/twill/letters/restoreRevision/{$letter->id}", 'GET', [
            'revisionId' => Letter::first()->revisions->last()->id,
        ]);
        $this->assertSee('You are currently editing an older revision of this content');
    }

    public function testBrowserBelongsTo()
    {
        $writers = $this->createWriters();
        $bio = $this->createBioWithWriter($writers[0]);

        // User can attach writers
        $this->assertNotEmpty(Bio::first()->writer);
        $this->assertEquals($writers[0]->id, Bio::first()->writer->id);
    }

    public function testBrowserBelongsToPreview()
    {
        $writers = $this->createWriters();
        $bio = $this->createBioWithWriter($writers[0]);

        // User can preview modifications
        $this->httpRequestAssert("/twill/bios/preview/{$bio->id}", 'PUT', [
            'browsers' => [
                'writer' => [
                    [
                        'id' => 3,
                        'name' => 'Charlie',
                        'endpointType' => 'App\\Models\\Writer',
                        'edit' => '',
                    ],
                ],
            ],
        ]);
        $this->assertSee('This is a bio');
        $this->assertSee('Writer: Charlie');
    }

    public function testBrowserBelongsToPreviewRevisions()
    {
        $writers = $this->createWriters();
        $bio = $this->createBioWithWriter($writers[0]);

        // User can preview revisions
        $this->httpRequestAssert("/twill/bios/preview/{$bio->id}", 'PUT', [
            'revisionId' => Bio::first()->revisions->first()->id,
        ]);
        $this->assertSee('This is a bio');
        $this->assertSee('No writer');
    }

    public function testBrowserBelongsToRestoreRevisions()
    {
        $writers = $this->createWriters();
        $bio = $this->createBioWithWriter($writers[0]);

        // User can restore revisions
        $this->httpRequestAssert("/twill/bios/restoreRevision/{$bio->id}", 'GET', [
            'revisionId' => Bio::first()->revisions->last()->id,
        ]);
        $this->assertSee('You are currently editing an older revision of this content');
    }

    public function testBrowserRelated()
    {
        $writers = $this->createWriters();
        $book = $this->createBookWithWriters($writers);

        // User can attach writers
        $this->assertEquals(3, RelatedItem::count());
        $this->assertEquals(3, Book::first()->getRelated('writers')->count());
        $this->assertEquals(
            $writers->pluck('id')->sort()->toArray(),
            Book::first()->getRelated('writers')->pluck('id')->sort()->toArray()
        );
    }

    public function testBrowserRelatedPreview()
    {
        $writers = $this->createWriters();
        $book = $this->createBookWithWriters($writers);

        // User can preview modifications
        $this->httpRequestAssert("/twill/books/preview/{$book->id}", 'PUT', [
            'browsers' => [
                'writers' => [
                    [
                        'id' => 3,
                        'name' => 'Charlie',
                        'endpointType' => 'App\\Models\\Writer',
                        'edit' => '',
                    ],
                ],
            ],
        ]);
        $this->assertSee('This is a book');
        $this->assertSee('Writers: Charlie');
    }

    public function testBrowserRelatedPreviewRevisions()
    {
        $writers = $this->createWriters();
        $book = $this->createBookWithWriters($writers);

        // User can preview revisions
        $this->httpRequestAssert("/twill/books/preview/{$book->id}", 'PUT', [
            'revisionId' => Book::first()->revisions->first()->id,
        ]);
        $this->assertSee('This is a book');
        $this->assertSee('No writers');
    }

    public function testBrowserRelatedRestoreRevisions()
    {
        $writers = $this->createWriters();
        $book = $this->createBookWithWriters($writers);

        // User can restore revisions
        $this->httpRequestAssert("/twill/books/restoreRevision/{$book->id}", 'GET', [
            'revisionId' => Book::first()->revisions->last()->id,
        ]);
        $this->assertSee('You are currently editing an older revision of this content');
    }

    public function testBrowserHasMany()
    {
        $writer = $this->createWriterWithBios();

        // User can attach bios
        $this->assertEquals(2, Bio::count());
        $this->assertEquals(2, $writer->bios()->count());
        Bio::all()->each(function ($bio) use ($writer) {
            $this->assertEquals($writer->id, $bio->writer_id);
        });
    }

    public function testBrowserHasManyPreview()
    {
        $writer = $this->createWriterWithBios();

        // User can preview modifications
        $this->httpRequestAssert("/twill/writers/preview/{$writer->id}", 'PUT', [
            'browsers' => [
                'bios' => [
                    [
                        'id' => 2,
                        'name' => 'Biography 2',
                        'endpointType' => 'App\\Models\\Writer',
                        'edit' => '',
                    ],
                ],
            ],
        ]);
        $this->assertSee('This is a writer');
        $this->assertSee('Bios: Biography 2');
    }

    public function testBrowserHasManyPreviewRevisions()
    {
        $writer = $this->createWriterWithBios();

        // User can preview revisions
        $this->httpRequestAssert("/twill/writers/preview/{$writer->id}", 'PUT', [
            'revisionId' => $writer->revisions->first()->id,
        ]);
        $this->assertSee('This is a writer');
        $this->assertSee('No bios');
    }

    public function testBrowserHasManyRestoreRevisions()
    {
        $writer = $this->createWriterWithBios();

        // User can restore revisions
        $this->httpRequestAssert("/twill/writers/restoreRevision/{$writer->id}", 'GET', [
            'revisionId' => $writer->revisions->last()->id,
        ]);
        $this->assertSee('You are currently editing an older revision of this content');
    }
}
