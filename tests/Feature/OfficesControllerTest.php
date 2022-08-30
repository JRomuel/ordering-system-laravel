<?php

namespace Tests\Feature;

use App\Models\Image;
use App\Models\Office;
use App\Models\Reservation;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class OfficesControllerTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_listAllOfficesPaginated()
    {
        Office::factory(3)->create();
        $response = $this->get('/api/offices');
        // dd($response->json());
        $response->assertStatus(200);
        $this->assertNotNull($response->json('data')[0]['id']);
        $this->assertNotNull($response->json('meta'));
        $this->assertNotNull($response->json('links'));
        $response->assertJsonCount(3, 'data');
    }
    public function test_listOnlyOfficesNotHiddenAndApproved()
    {
        Office::factory(3)->create();
        Office::factory()->create(['hidden' => true]);
        Office::factory()->create(['approval_status' => Office::APPROVAL_PENDING]);
        $response = $this->get('/api/offices');
        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }
    public function test_filterByUserId()
    {
        Office::factory(3)->create();
        $host = User::factory()->create();
        $office = Office::factory()->for($host)->create();

        $response = $this->get(
            '/api/offices?user_id='.$host->id
        );
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $this->assertEquals($office->id, $response->json('data')[0]['id']);
    }
    public function test_filterByVisitorId()
    {
        Office::factory(3)->create();
        $user = User::factory()->create();
        $office = Office::factory()->create();
        Reservation::factory()->for(Office::factory())->create();
        Reservation::factory()->for($office)->for($user)->create();

        $response = $this->get(
            '/api/offices?visitor_id='.$user->id
        );
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $this->assertEquals($office->id, $response->json('data')[0]['id']);
    }
    public function test_includesImagesTagsAndUser()
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->create();

        $office = Office::factory()->for($user)->create();
        $office->tags()->attach($tag);
        $office->images()->create(['path' => 'image.jpg']);

        // dd($office->images);

        $response = $this->get('/api/offices');

        $response->assertOk();
        $this->assertIsArray($response->json('data')[0]['tags']);
        $this->assertCount(1, $response->json('data')[0]['tags']);
        $this->assertIsArray($response->json('data')[0]['images']);
        $this->assertCount(1, $response->json('data')[0]['images']);
        $this->assertEquals($user->id, $response->json('data')[0]['user']['id']);
    }

    public function test_returnsNumberOfActiveReservations()
    {
        $office = Office::factory()->create();
        Reservation::factory()->for($office)->create(['status' => Reservation::STATUS_ACTIVE]);
        Reservation::factory()->for($office)->create(['status' => Reservation::STATUS_CANCELLED]);
        $response = $this->get('/api/offices');
        $response->assertOk();
        $this->assertEquals(1, $response->json('data')[0]['reservations_count']);
        // $response->dump();



    }

    public function test_itOrderByDistanceWhenCoordinateAreProvided()
    {
        // 'lat' => '38.720661384644046',
        // 'lng' => '-9.16044783453807',
        // office 2
  
        $office = Office::factory()->create([
            'lat' => '39.740517284644046',
            'lng' => '-8.7703753783453807',
            'title' => 'Leiria',
        ]);
        $office = Office::factory()->create([
            'lat' => '39.0775661384644046',
            'lng' => '-9.281266783453807',
            'title' => 'Torres Verdas',
        ]);
  

        $response = $this->get('/api/offices?lat=38.720661384644046&lng=-9.16044783453807');
        $response->assertOk();
        $this->assertEquals('Torres Verdas', $response->json('data')[0]['title']);
        $this->assertEquals('Leiria', $response->json('data')[1]['title']);

        $response = $this->get('/api/offices');
        $response->assertOk();
       $this->assertEquals('Leiria', $response->json('data')[0]['title']);
        $this->assertEquals('Torres Verdas', $response->json('data')[1]['title']);
        
    }

    public function test_showsTheOffice()
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->create();

        $office = Office::factory()->for($user)->create();
        $office->tags()->attach($tag);
        $office->images()->create(['path' => 'image.jpg']);

        Reservation::factory()->for($office)->create(['status' => Reservation::STATUS_ACTIVE]);
        Reservation::factory()->for($office)->create(['status' => Reservation::STATUS_CANCELLED]);

        $response = $this->get('/api/offices/'.$office->id);
        
        $response->assertOk();
        $this->assertEquals(1, $response->json('data')['reservations_count']);
        $this->assertIsArray($response->json('data')['tags']);
        $this->assertCount(1, $response->json('data')['tags']);
        $this->assertIsArray($response->json('data')['images']);
        $this->assertCount(1, $response->json('data')['images']);
        $this->assertEquals($user->id, $response->json('data')['user']['id']);
    }

    public function test_createAnOffice()
    {
        $user = User::factory()->createQuietly();
        $tag = Tag::factory()->create();
        $tag2 = Tag::factory()->create();

        $this->actingAs($user);
        $response = $this->postJson('/api/offices', [
            'title' => 'Office in Manila',
            'description' => 'Descriptions',
            'lat' => '39.740517284644046',
            'lng' => '-8.7703753783453807',
            'address_line1' => 'address',
            'price_per_day' => 10_000,
            'monthly_discount' => 5,
            'tags' => [
                $tag->id, $tag2->id
            ]
        ]); 
 
        // dd($response->json());

        $response->assertCreated()
            ->assertJsonPath('data.title','Office in Manila')           
            ->assertJsonPath('data.user.id', $user->id)           
            ->assertJsonPath('data.approval_status', Office::APPROVAL_PENDING)           
            ->assertJsonCount(2,'data.tags');
            
        // test if the model persist in the database
        $this->assertDatabaseHas('offices', [
            'title' => 'office in Manila'
        ]);
    }
}
