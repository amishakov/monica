<?php

namespace Tests\Features\Contact\ManageContactInformation\Services;

use Tests\TestCase;
use App\Models\User;
use App\Models\Vault;
use App\Models\Account;
use App\Models\Contact;
use App\Jobs\CreateAuditLog;
use App\Jobs\CreateContactLog;
use Illuminate\Support\Facades\Queue;
use App\Models\ContactInformationType;
use Illuminate\Validation\ValidationException;
use App\Exceptions\NotEnoughPermissionException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Features\Contact\ManageContactInformation\Services\CreateContactInformation;

class CreateContactInformationTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_creates_a_contact_information(): void
    {
        $regis = $this->createUser();
        $vault = $this->createVault($regis->account);
        $vault = $this->setPermissionInVault($regis, Vault::PERMISSION_EDIT, $vault);
        $contact = Contact::factory()->create(['vault_id' => $vault->id]);
        $type = ContactInformationType::factory()->create(['account_id' => $regis->account_id]);

        $this->executeService($regis, $regis->account, $vault, $contact, $type);
    }

    /** @test */
    public function it_fails_if_wrong_parameters_are_given(): void
    {
        $request = [
            'title' => 'Ross',
        ];

        $this->expectException(ValidationException::class);
        (new CreateContactInformation)->execute($request);
    }

    /** @test */
    public function it_fails_if_user_doesnt_belong_to_account(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $regis = $this->createUser();
        $account = Account::factory()->create();
        $vault = $this->createVault($regis->account);
        $vault = $this->setPermissionInVault($regis, Vault::PERMISSION_EDIT, $vault);
        $contact = Contact::factory()->create(['vault_id' => $vault->id]);
        $type = ContactInformationType::factory()->create(['account_id' => $regis->account_id]);

        $this->executeService($regis, $account, $vault, $contact, $type);
    }

    /** @test */
    public function it_fails_if_contact_doesnt_belong_to_vault(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $regis = $this->createUser();
        $vault = $this->createVault($regis->account);
        $vault = $this->setPermissionInVault($regis, Vault::PERMISSION_EDIT, $vault);
        $contact = Contact::factory()->create();
        $type = ContactInformationType::factory()->create(['account_id' => $regis->account_id]);

        $this->executeService($regis, $regis->account, $vault, $contact, $type);
    }

    /** @test */
    public function it_fails_if_user_doesnt_have_right_permission_in_initial_vault(): void
    {
        $this->expectException(NotEnoughPermissionException::class);

        $regis = $this->createUser();
        $vault = $this->createVault($regis->account);
        $vault = $this->setPermissionInVault($regis, Vault::PERMISSION_VIEW, $vault);
        $contact = Contact::factory()->create(['vault_id' => $vault->id]);
        $type = ContactInformationType::factory()->create(['account_id' => $regis->account_id]);

        $this->executeService($regis, $regis->account, $vault, $contact, $type);
    }

    /** @test */
    public function it_fails_if_type_is_not_in_the_account(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $regis = $this->createUser();
        $vault = $this->createVault($regis->account);
        $vault = $this->setPermissionInVault($regis, Vault::PERMISSION_EDIT, $vault);
        $contact = Contact::factory()->create(['vault_id' => $vault->id]);
        $type = ContactInformationType::factory()->create();

        $this->executeService($regis, $regis->account, $vault, $contact, $type);
    }

    private function executeService(User $author, Account $account, Vault $vault, Contact $contact, ContactInformationType $type): void
    {
        Queue::fake();

        $request = [
            'account_id' => $account->id,
            'vault_id' => $vault->id,
            'author_id' => $author->id,
            'contact_id' => $contact->id,
            'contact_information_type_id' => $type->id,
            'data' => '45322322',
        ];

        $contactInfo = (new CreateContactInformation)->execute($request);

        $this->assertDatabaseHas('contact_information', [
            'id' => $contactInfo->id,
            'data' => 45322322,
        ]);

        Queue::assertPushed(CreateAuditLog::class, function ($job) {
            return $job->auditLog['action_name'] === 'contact_information_created';
        });

        Queue::assertPushed(CreateContactLog::class, function ($job) {
            return $job->contactLog['action_name'] === 'contact_information_created';
        });
    }
}
