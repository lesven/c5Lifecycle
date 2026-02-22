<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class FormControllerTest extends WebTestCase
{
    public function testIndexPageRendersSuccessfully(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Evidence erfassen');
    }

    public function testIndexPageContainsAllFormLinks(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $links = $crawler->filter('.form-card');
        $this->assertCount(7, $links);
    }

    /**
     * @dataProvider formSlugProvider
     */
    public function testFormPageRendersSuccessfully(string $slug, string $expectedTitle): void
    {
        $client = static::createClient();
        $client->request('GET', '/forms/' . $slug);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', $expectedTitle);
    }

    public static function formSlugProvider(): array
    {
        return [
            ['rz-provision', 'Inbetriebnahme RZ-Asset'],
            ['rz-retire', 'Außerbetriebnahme RZ-Asset'],
            ['rz-owner-confirm', 'Owner-Betriebsbestätigung'],
            ['admin-provision', 'Inbetriebnahme Admin-Endgerät'],
            ['admin-user-commitment', 'Verpflichtung Admin-User'],
            ['admin-return', 'Rückgabe Admin-Endgerät'],
            ['admin-access-cleanup', 'Privileged Access Cleanup (IAM)'],
        ];
    }

    public function testFormPageHasEvidenceFormWithDataEvent(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/forms/rz-provision');

        $this->assertResponseIsSuccessful();
        $form = $crawler->filter('#evidence-form');
        $this->assertCount(1, $form);
        $this->assertEquals('rz_provision', $form->attr('data-event'));
    }

    public function testFormPageHasFormStatusDiv(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/forms/rz-provision');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('#form-status'));
    }

    public function testFormPageHasAssetIdField(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/forms/rz-provision');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('#asset_id'));
    }

    public function testUnknownFormSlugReturns404(): void
    {
        $client = static::createClient();
        $client->request('GET', '/forms/nonexistent');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testRetireFormHasConditionalRequiredField(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/forms/rz-retire');

        $this->assertResponseIsSuccessful();
        $conditionalField = $crawler->filter('[data-conditional-required]');
        $this->assertGreaterThan(0, $conditionalField->count());
    }

    public function testAccessCleanupFormHasConditionalRequiredField(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/forms/admin-access-cleanup');

        $this->assertResponseIsSuccessful();
        $conditionalField = $crawler->filter('[data-conditional-required]');
        $this->assertGreaterThan(0, $conditionalField->count());
    }
}
