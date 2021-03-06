<?php
namespace Dilab\CakeMongo\Test\TestCase;

use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;
use Dilab\CakeMongo\Collection;
use Dilab\CakeMongo\Document;
use Dilab\CakeMongo\Marshaller;

/**
 * Test entity for mass assignment.
 */
class ProtectedArticle extends Document
{

    protected $_accessible = [
        'title' => true,
    ];
}

class MarshallerTest extends TestCase
{
    /**
     * Fixtures for this test.
     *
     * @var array
     */
    public $fixtures = ['plugin.dilab/cake_mongo.articles'];

    /**
     * @var Collection
     */
    private $collection;

    /**
     * Setup method.
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $this->collection = new Collection([
            'name' => 'articles',
            'connection' => ConnectionManager::get('test')
        ]);
    }

    /**
     * test marshalling a simple object.
     *
     * @return void
     */
    public function testOneSimple()
    {
        $data = [
            'title' => 'Testing',
            'body' => 'MongoDB text',
            'user_id' => 1,
        ];
        $marshaller = new Marshaller($this->collection);
        $result = $marshaller->one($data);

        $this->assertInstanceOf('Dilab\CakeMongo\Document', $result);
        $this->assertSame($data['title'], $result->title);
        $this->assertSame($data['body'], $result->body);
        $this->assertSame($data['user_id'], $result->user_id);
    }

    /**
     * Test validation errors being set.
     *
     * @return void
     */
    public function testOneValidationErrorsSet()
    {
        $data = [
            'title' => 'Testing',
            'body' => 'MongoDB text',
            'user_id' => 1,
        ];
        $this->collection->validator()
            ->add('title', 'numbery', ['rule' => 'numeric']);

        $marshaller = new Marshaller($this->collection);
        $result = $marshaller->one($data);

        $this->assertInstanceOf('Dilab\CakeMongo\Document', $result);
        $this->assertNull($result->title, 'Invalid fields are not set.');
        $this->assertSame($data['body'], $result->body);
        $this->assertSame($data['user_id'], $result->user_id);
        $this->assertNotEmpty($result->errors('title'), 'Should have an error.');
    }

    /**
     * test marshalling with fieldList
     *
     * @return void
     */
    public function testOneFieldList()
    {
        $data = [
            'title' => 'Testing',
            'body' => 'MongoDB text',
            'user_id' => 1,
        ];
        $marshaller = new Marshaller($this->collection);
        $result = $marshaller->one($data, ['fieldList' => ['title']]);

        $this->assertSame($data['title'], $result->title);
        $this->assertNull($result->body);
        $this->assertNull($result->user_id);
    }

    /**
     * test marshalling with accessibleFields
     *
     * @return void
     */
    public function testOneAccsesibleFields()
    {
        $data = [
            'title' => 'Testing',
            'body' => 'MongoDB text',
            'user_id' => 1,
        ];
        $this->collection->entityClass(__NAMESPACE__ . '\ProtectedArticle');

        $marshaller = new Marshaller($this->collection);
        $result = $marshaller->one($data);

        $this->assertSame($data['title'], $result->title);
        $this->assertNull($result->body);
        $this->assertNull($result->user_id);

        $result = $marshaller->one($data, ['accessibleFields' => ['body' => true]]);

        $this->assertSame($data['title'], $result->title);
        $this->assertSame($data['body'], $result->body);
        $this->assertNull($result->user_id);
    }

    /**
     * test beforeMarshal event
     *
     * @return void
     */
    public function testOneBeforeMarshalEvent()
    {
        $data = [
            'title' => 'Testing',
            'body' => 'MongoDB text',
            'user_id' => 1,
        ];
        $called = 0;
        $this->collection->eventManager()->on(
            'Model.beforeMarshal',
            function ($event, $data, $options) use (&$called) {
                $called++;
                $this->assertInstanceOf('ArrayObject', $data);
                $this->assertInstanceOf('ArrayObject', $options);
            }
        );
        $marshaller = new Marshaller($this->collection);
        $marshaller->one($data);

        $this->assertEquals(1, $called, 'method should be called');
    }

    /**
     * test beforeMarshal event allows data mutation.
     *
     * @return void
     */
    public function testOneBeforeMarshalEventMutateData()
    {
        $data = [
            'title' => 'Testing',
            'body' => 'MongoDB text',
            'user_id' => 1,
        ];
        $this->collection->eventManager()->on('Model.beforeMarshal', function ($event, $data, $options) {
            $data['title'] = 'Mutated';
        });
        $marshaller = new Marshaller($this->collection);
        $result = $marshaller->one($data);
        $this->assertEquals('Mutated', $result->title);
    }

    /**
     * test marshalling a simple object.
     *
     * @return void
     */
    public function testOneEmbeddedOne()
    {
        $this->markTestSkipped('Embed Not Implemented Yet');
        $data = [
            'title' => 'Testing',
            'body' => 'MongoDB text',
            'user' => [
                'username' => 'mark',
            ],
        ];
        $this->type->embedOne('User');

        $marshaller = new Marshaller($this->type);
        $result = $marshaller->one($data, ['associated' => ['User']]);

        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result);
        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result->user);
        $this->assertSame($data['title'], $result->title);
        $this->assertSame($data['body'], $result->body);
        $this->assertSame($data['user']['username'], $result->user->username);

        $marshaller = new Marshaller($this->type);
        $result = $marshaller->one($data);

        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result);
        $this->assertInternalType('array', $result->user);
        $this->assertSame($data['title'], $result->title);
        $this->assertSame($data['body'], $result->body);
        $this->assertSame($data['user']['username'], $result->user['username']);
    }

    /**
     * test marshalling a simple object.
     *
     * @return void
     */
    public function testOneEmbeddedMany()
    {
        $this->markTestSkipped('Embed Not Implemented Yet');
        $data = [
            'title' => 'Testing',
            'body' => 'MongoDB text',
            'comments' => [
                ['comment' => 'First comment'],
                ['comment' => 'Second comment'],
                'bad' => 'data'
            ],
        ];
        $this->type->embedMany('Comments');

        $marshaller = new Marshaller($this->type);
        $result = $marshaller->one($data, ['associated' => ['Comments']]);

        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result);
        $this->assertInternalType('array', $result->comments);
        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result->comments[0]);
        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result->comments[1]);
        $this->assertTrue($result->isNew());
        $this->assertTrue($result->comments[0]->isNew());
        $this->assertTrue($result->comments[1]->isNew());
    }

    /**
     * Test converting multiple objects at once.
     *
     * @return void
     */
    public function testMany()
    {
        $data = [
            [
                'title' => 'Testing',
                'body' => 'MongoDB text',
                'user_id' => 1,
            ],
            [
                'title' => 'Second article',
                'body' => 'Stretchy text',
                'user_id' => 2,
            ]
        ];
        $marshaller = new Marshaller($this->collection);
        $result = $marshaller->many($data);

        $this->assertCount(2, $result);
        $this->assertInstanceOf('Dilab\CakeMongo\Document', $result[0]);
        $this->assertInstanceOf('Dilab\CakeMongo\Document', $result[1]);
        $this->assertSame($data[0], $result[0]->toArray());
        $this->assertSame($data[1], $result[1]->toArray());
    }

    /**
     * Test merging data into existing records.
     *
     * @return void
     */
    public function testMerge()
    {
        $doc = $this->collection->get('507f191e810c19729de860ea');
        $data = [
            'title' => 'New title',
            'body' => 'Updated',
        ];
        $marshaller = new Marshaller($this->collection);
        $result = $marshaller->merge($doc, $data);

        $this->assertSame($result, $doc, 'Object should be the same.');
        $this->assertSame($data['title'], $doc->title, 'title should be the same.');
        $this->assertSame($data['body'], $doc->body, 'body should be the same.');
        $this->assertTrue($doc->dirty('title'));
        $this->assertTrue($doc->dirty('body'));
        $this->assertFalse($doc->dirty('user_id'));
        $this->assertFalse($doc->isNew(), 'Should not end up new');
    }

    /**
     * Test validation errors being set.
     *
     * @return void
     */
    public function testMergeValidationErrorsSet()
    {
        $data = [
            'title' => 'Testing',
            'body' => 'MongoDB text',
            'user_id' => 1,
        ];
        $this->collection->validator()->add('title', 'numbery', ['rule' => 'numeric']);
        $doc = $this->collection->get('507f191e810c19729de860ea');

        $marshaller = new Marshaller($this->collection);
        $result = $marshaller->merge($doc, $data);

        $this->assertInstanceOf('Dilab\CakeMongo\Document', $result);
        $this->assertSame('First article', $result->title, 'Invalid fields are not modified.');
        $this->assertNotEmpty($result->errors('title'), 'Should have an error.');
    }

    /**
     * Test merging data into existing records with a fieldlist
     *
     * @return void
     */
    public function testMergeFieldList()
    {
        $doc = $this->collection->get('507f191e810c19729de860ea');
        $doc->accessible('*', false);

        $data = [
            'title' => 'New title',
            'body' => 'Updated',
        ];
        $marshaller = new Marshaller($this->collection);
        $result = $marshaller->merge($doc, $data, ['fieldList' => ['title']]);

        $this->assertSame($result, $doc, 'Object should be the same.');
        $this->assertSame($data['title'], $doc->title, 'title should be the same.');
        $this->assertNotEquals($data['body'], $doc->body, 'body should be the same.');
        $this->assertTrue($doc->dirty('title'));
        $this->assertFalse($doc->dirty('body'));
    }

    /**
     * test beforeMarshal event
     *
     * @return void
     */
    public function testMergeBeforeMarshalEvent()
    {
        $data = [
            'title' => 'Testing',
            'body' => 'MongoDB text',
            'user_id' => 1,
        ];
        $called = 0;
        $this->collection->eventManager()->on(
            'Model.beforeMarshal',
            function ($event, $data, $options) use (&$called) {
                $called++;
                $this->assertInstanceOf('ArrayObject', $data);
                $this->assertInstanceOf('ArrayObject', $options);
            }
        );
        $marshaller = new Marshaller($this->collection);
        $doc = new Document(['title' => 'original', 'body' => 'original']);
        $marshaller->merge($doc, $data);

        $this->assertEquals(1, $called, 'method should be called');
    }

    /**
     * test beforeMarshal event allows data mutation.
     *
     * @return void
     */
    public function testMergeBeforeMarshalEventMutateData()
    {
        $data = [
            'title' => 'Testing',
            'body' => 'MongoDB text',
            'user_id' => 1,
        ];
        $this->collection->eventManager()->on('Model.beforeMarshal', function ($event, $data, $options) {
            $data['title'] = 'Mutated';
        });
        $marshaller = new Marshaller($this->collection);
        $doc = new Document(['title' => 'original', 'body' => 'original']);
        $result = $marshaller->merge($doc, $data);
        $this->assertEquals('Mutated', $result->title);
    }

    /**
     * test merge with an embed one
     *
     * @return void
     */
    public function testMergeEmbeddedOneExisting()
    {
        $this->markTestSkipped('Embed Not Implemented Yet');
        $this->type->embedOne('User');
        $data = [
            'title' => 'Testing',
            'body' => 'MongoDB text',
            'user' => [
                'username' => 'mark',
            ],
        ];
        $entity = new Document([
            'title' => 'Old',
            'user' => new Document(['username' => 'old'], ['markNew' => false])
        ], ['markNew' => false]);

        $marshaller = new Marshaller($this->type);
        $result = $marshaller->merge($entity, $data, ['associated' => ['User']]);

        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result);
        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result->user);
        $this->assertFalse($result->isNew(), 'Existing doc');
        $this->assertFalse($result->user->isNew(), 'Existing sub-doc');
        $this->assertSame($data['title'], $result->title);
        $this->assertSame($data['body'], $result->body);
        $this->assertSame($data['user']['username'], $result->user->username);
    }

    /**
     * test merge when embedded documents don't exist
     *
     * @return void
     */
    public function testMergeEmbeddedOneMissing()
    {
        $this->markTestSkipped('Embed Not Implemented Yet');

        $this->type->embedOne('User');
        $data = [
            'title' => 'Testing',
            'body' => 'MongoDB text',
            'user' => [
                'username' => 'mark',
            ],
        ];
        $entity = new Document([
            'title' => 'Old',
        ], ['markNew' => false]);

        $marshaller = new Marshaller($this->type);
        $result = $marshaller->merge($entity, $data, ['associated' => ['User']]);

        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result);
        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result->user);
        $this->assertSame($data['title'], $result->title);
        $this->assertSame($data['body'], $result->body);
        $this->assertSame($data['user']['username'], $result->user->username);
        $this->assertTrue($result->user->isNew(), 'Was missing, should now be new.');
    }

    /**
     * test marshalling a simple object.
     *
     * @return void
     */
    public function testMergeEmbeddedMany()
    {
        $this->markTestSkipped('Embed Not Implemented Yet');

        $data = [
            'title' => 'Testing',
            'body' => 'MongoDB text',
            'comments' => [
                ['comment' => 'First comment'],
                ['comment' => 'Second comment'],
                'bad' => 'data'
            ],
        ];
        $this->type->embedMany('Comments');

        $entity = new Document([
            'title' => 'old',
            'comments' => [
                new Document(['comment' => 'old'], ['markNew' => false]),
                new Document(['comment' => 'old'], ['markNew' => false]),
            ]
        ], ['markNew' => false]);

        $marshaller = new Marshaller($this->type);
        $result = $marshaller->merge($entity, $data, ['associated' => ['Comments']]);

        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result);
        $this->assertInternalType('array', $result->comments);
        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result->comments[0]);
        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result->comments[1]);
        $this->assertFalse($result->comments[0]->isNew());
        $this->assertFalse($result->comments[1]->isNew());
    }

    /**
     * test merge with some sub documents not existing.
     *
     * @return void
     */
    public function testMergeEmbeddedManySomeMissing()
    {
        $this->markTestSkipped('Embed Not Implemented Yet');

        $data = [
            'title' => 'Testing',
            'body' => 'MongoDB text',
            'comments' => [
                ['comment' => 'First comment'],
                ['comment' => 'Second comment'],
                'bad' => 'data'
            ],
        ];
        $entity = new Document([
            'title' => 'old',
            'comments' => [
                new Document(['comment' => 'old'], ['markNew' => false]),
            ]
        ], ['markNew' => false]);

        $this->type->embedMany('Comments');

        $marshaller = new Marshaller($this->type);
        $result = $marshaller->merge($entity, $data, ['associated' => ['Comments']]);

        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result);
        $this->assertInternalType('array', $result->comments);

        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result->comments[0]);
        $this->assertSame('First comment', $result->comments[0]->comment);
        $this->assertFalse($result->comments[0]->isNew());

        $this->assertInstanceOf('Cake\ElasticSearch\Document', $result->comments[1]);
        $this->assertSame('Second comment', $result->comments[1]->comment);
        $this->assertTrue($result->comments[1]->isNew());
    }

    /**
     * Test that mergeMany will create new objects if the entity list is empty.
     *
     * @return void
     */
    public function testMergeManyAllNew()
    {
        $entities = [];
        $data = [
            [
                'title' => 'New first',
            ],
            [
                'title' => 'New second',
            ],
        ];
        $marshaller = new Marshaller($this->collection);
        $result = $marshaller->mergeMany($entities, $data);

        $this->assertCount(2, $result);
        $this->assertSame($data[0], $result[0]->toArray());
        $this->assertSame($data[1], $result[1]->toArray());
    }

    /**
     * Ensure that mergeMany uses the fieldList option.
     *
     * @return void
     */
    public function testMergeManyFieldList()
    {
        $entities = [];
        $data = [
            [
                'title' => 'New first',
                'body' => 'Nope',
            ],
            [
                'title' => 'New second',
                'body' => 'Nope',
            ],
        ];
        $marshaller = new Marshaller($this->collection);
        $result = $marshaller->mergeMany($entities, $data, ['fieldList' => ['title']]);

        $this->assertCount(2, $result);
        $this->assertNull($result[0]->body);
        $this->assertNull($result[1]->body);
    }

    /**
     * Ensure that mergeMany can merge a sparse data set.
     *
     * @return void
     */
    public function testMergeManySomeNew()
    {
        $doc = $this->collection->get('507f191e810c19729de860ea');
        $entities = [$doc];

        $data = [
            [
                'id' => '507f191e810c19729de860ea',
                'title' => 'New first',
            ],
            [
                'title' => 'New second',
            ],
        ];
        $marshaller = new Marshaller($this->collection);
        $result = $marshaller->mergeMany($entities, $data);

        $this->assertCount(2, $result);
        $this->assertEquals($data[0]['title'], $result[0]->title);
        $this->assertFalse($result[0]->isNew());
        $this->assertTrue($result[0]->dirty());
        $this->assertTrue($result[0]->dirty('title'));

        $this->assertTrue($result[1]->isNew());
        $this->assertTrue($result[1]->dirty());
        $this->assertTrue($result[1]->dirty('title'));
    }

    /**
     * Test that unknown entities are excluded from the results.
     *
     * @return void
     */
    public function testMergeManyDropsUnknownEntities()
    {
        $doc = $this->collection->get('507f191e810c19729de860ea');
        $entities = [$doc];

        $data = [
            [
                'id' => 2,
                'title' => 'New first',
            ],
            [
                'title' => 'New third',
            ],
        ];
        $marshaller = new Marshaller($this->collection);
        $result = $marshaller->mergeMany($entities, $data);

        $this->assertCount(2, $result);
        $this->assertEquals($data[0], $result[0]->toArray());
        $this->assertTrue($result[0]->isNew());
        $this->assertTrue($result[0]->dirty());
        $this->assertTrue($result[0]->dirty('title'));

        $this->assertEquals($data[1], $result[1]->toArray());
        $this->assertTrue($result[1]->isNew());
        $this->assertTrue($result[1]->dirty());
        $this->assertTrue($result[1]->dirty('title'));
    }

    /**
     * Ensure that only entities are updated.
     *
     * @return void
     */
    public function testMergeManyBadEntityData()
    {
        $doc = $this->collection->get('507f191e810c19729de860ea');
        $entities = ['string', ['herp' => 'derp']];

        $data = [
            [
                'title' => 'New first',
            ],
        ];
        $marshaller = new Marshaller($this->collection);
        $result = $marshaller->mergeMany($entities, $data);

        $this->assertCount(1, $result);
        $this->assertEquals($data[0], $result[0]->toArray());
    }

}
