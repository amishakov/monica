<?php

namespace App\Settings\ManageUsers\Services;

use App\Interfaces\ServiceInterface;
use App\Models\User;
use App\Models\Vault;
use App\Services\BaseService;
use Dotenv\Exception\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class DestroyUser extends BaseService implements ServiceInterface
{
    private User $user;

    private array $data;

    /**
     * Get the validation rules that apply to the service.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'account_id' => 'required|integer|exists:accounts,id',
            'author_id' => 'required|integer|exists:users,id',
            'user_id' => 'required|integer|exists:users,id',
        ];
    }

    /**
     * Get the permissions that apply to the user calling the service.
     *
     * @return array
     */
    public function permissions(): array
    {
        return [
            'author_must_belong_to_account',
            'author_must_be_account_administrator',
        ];
    }

    /**
     * Destroy the given user.
     * The user who calls the service can't delete himself.
     *
     * @param  array  $data
     * @return void
     */
    public function execute(array $data): void
    {
        $this->data = $data;

        $this->validate();
        $this->destroyAllVaults();
        $this->destroy();
    }

    private function validate(): void
    {
        $this->validateRules($this->data);

        $this->user = User::where('account_id', $this->data['account_id'])
            ->findOrFail($this->data['user_id']);

        if ($this->data['user_id'] === $this->data['author_id']) {
            throw new ValidationException(
                'You can\'t delete yourself.',
            );
        }
    }

    /**
     * We will destroy all the vaults the user is the manager of, IF there are
     * no other managers of the vault.
     *
     * @return void
     */
    private function destroyAllVaults(): void
    {
        $vaultsUserIsManagerOf = $this->user->vaults()
            ->wherePivot('permission', Vault::PERMISSION_MANAGE)
            ->get();

        foreach ($vaultsUserIsManagerOf as $vault) {
            try {
                $vault->users()
                    ->wherePivot('user_id', '!=', $this->user->id)
                    ->wherePivot('permission', Vault::PERMISSION_MANAGE)
                    ->firstOrFail();
            } catch (ModelNotFoundException) {
                $vault->delete();
            }
        }
    }

    private function destroy(): void
    {
        $this->user->delete();
    }
}