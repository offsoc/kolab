<template>
    <div class="container">
        <div class="card" id="user-info">
            <div class="card-body">
                <h1 class="card-title">{{ user.email }}</h1>
                <div class="card-text">
                    <form class="read-only short">
                        <div v-if="user.wallet.user_id != user.id" class="row plaintext">
                            <label for="manager" class="col-sm-4 col-form-label">{{ $t('user.managed-by') }}</label>
                            <div class="col-sm-8">
                                <span class="form-control-plaintext" id="manager">
                                    <router-link :to="{ path: '/user/' + user.wallet.user_id }">{{ user.wallet.user_email }}</router-link>
                                </span>
                            </div>
                        </div>
                        <div class="row plaintext">
                            <label for="userid" class="col-sm-4 col-form-label">ID <span class="text-muted">({{ $t('form.created') }})</span></label>
                            <div class="col-sm-8">
                                <span class="form-control-plaintext" id="userid">
                                    {{ user.id }} <span class="text-muted">({{ user.created_at }})</span>
                                </span>
                            </div>
                        </div>
                        <div class="row plaintext">
                            <label for="status" class="col-sm-4 col-form-label">{{ $t('form.status') }}</label>
                            <div class="col-sm-8">
                                <span class="form-control-plaintext" id="status">
                                    <span :class="$root.userStatusClass(user)">{{ $root.userStatusText(user) }}</span>
                                </span>
                            </div>
                        </div>
                        <div class="row plaintext" v-if="user.first_name">
                            <label for="first_name" class="col-sm-4 col-form-label">{{ $t('form.firstname') }}</label>
                            <div class="col-sm-8">
                                <span class="form-control-plaintext" id="first_name">{{ user.first_name }}</span>
                            </div>
                        </div>
                        <div class="row plaintext" v-if="user.last_name">
                            <label for="last_name" class="col-sm-4 col-form-label">{{ $t('form.lastname') }}</label>
                            <div class="col-sm-8">
                                <span class="form-control-plaintext" id="last_name">{{ user.last_name }}</span>
                            </div>
                        </div>
                        <div class="row plaintext" v-if="user.organization">
                            <label for="organization" class="col-sm-4 col-form-label">{{ $t('user.org') }}</label>
                            <div class="col-sm-8">
                                <span class="form-control-plaintext" id="organization">{{ user.organization }}</span>
                            </div>
                        </div>
                        <div class="row plaintext" v-if="user.phone">
                            <label for="phone" class="col-sm-4 col-form-label">{{ $t('form.phone') }}</label>
                            <div class="col-sm-8">
                                <span class="form-control-plaintext" id="phone">{{ user.phone }}</span>
                            </div>
                        </div>
                        <div class="row plaintext">
                            <label for="external_email" class="col-sm-4 col-form-label">{{ $t('user.ext-email') }}</label>
                            <div class="col-sm-8">
                                <span class="form-control-plaintext" id="external_email">
                                    <a v-if="user.external_email" :href="'mailto:' + user.external_email">{{ user.external_email }}</a>
                                    <button type="button" class="btn btn-secondary btn-sm" @click="emailEdit">{{ $t('btn.edit') }}</button>
                                </span>
                            </div>
                        </div>
                        <div class="row plaintext" v-if="user.billing_address">
                            <label for="billing_address" class="col-sm-4 col-form-label">{{ $t('user.address') }}</label>
                            <div class="col-sm-8">
                                <span class="form-control-plaintext" style="white-space:pre" id="billing_address">{{ user.billing_address }}</span>
                            </div>
                        </div>
                        <div class="row plaintext">
                            <label for="country" class="col-sm-4 col-form-label">{{ $t('user.country') }}</label>
                            <div class="col-sm-8">
                                <span class="form-control-plaintext" id="country">{{ user.country }}</span>
                            </div>
                        </div>
                    </form>
                    <div class="mt-2">
                        <button v-if="!user.isSuspended" id="button-suspend" class="btn btn-warning" type="button" @click="suspendUser">
                            {{ $t('btn.suspend') }}
                        </button>
                        <button v-if="user.isSuspended" id="button-unsuspend" class="btn btn-warning" type="button" @click="unsuspendUser">
                            {{ $t('btn.unsuspend') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <ul class="nav nav-tabs mt-3" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="tab-finances" href="#user-finances" role="tab" aria-controls="user-finances" aria-selected="true">
                    {{ $t('user.finances') }}
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="tab-aliases" href="#user-aliases" role="tab" aria-controls="user-aliases" aria-selected="false">
                    {{ $t('user.aliases') }} ({{ user.aliases.length }})
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="tab-subscriptions" href="#user-subscriptions" role="tab" aria-controls="user-subscriptions" aria-selected="false">
                    {{ $t('user.subscriptions') }} ({{ skus.length }})
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="tab-domains" href="#user-domains" role="tab" aria-controls="user-domains" aria-selected="false">
                    {{ $t('user.domains') }} ({{ domains.length }})
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="tab-users" href="#user-users" role="tab" aria-controls="user-users" aria-selected="false">
                    {{ $t('user.users') }} ({{ users.length }})
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="tab-distlists" href="#user-distlists" role="tab" aria-controls="user-distlists" aria-selected="false">
                    {{ $t('user.distlists') }} ({{ distlists.length }})
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="tab-resources" href="#user-resources" role="tab" aria-controls="user-resources" aria-selected="false">
                    {{ $t('user.resources') }} ({{ resources.length }})
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="tab-shared-folders" href="#user-shared-folders" role="tab" aria-controls="user-shared-folders" aria-selected="false">
                    {{ $t('dashboard.shared-folders') }} ({{ folders.length }})
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="tab-settings" href="#user-settings" role="tab" aria-controls="user-settings" aria-selected="false">
                    {{ $t('form.settings') }}
                </a>
            </li>
        </ul>
        <div class="tab-content">
            <div class="tab-pane show active" id="user-finances" role="tabpanel" aria-labelledby="tab-finances">
                <div class="card-body">
                    <h2 class="card-title">
                        {{ $t('wallet.title') }}
                        <span :class="wallet.balance < 0 ? 'text-danger' : 'text-success'"><strong>{{ $root.price(wallet.balance, wallet.currency) }}</strong></span>
                    </h2>
                    <div class="card-text">
                        <form class="read-only short">
                            <div class="row">
                                <label class="col-sm-4 col-form-label">{{ $t('user.discount') }}</label>
                                <div class="col-sm-8">
                                    <span class="form-control-plaintext" id="discount">
                                        <span>{{ wallet.discount ? (wallet.discount + '% - ' + wallet.discount_description) : 'none' }}</span>
                                        <button type="button" class="btn btn-secondary btn-sm" @click="discountEdit">{{ $t('btn.edit') }}</button>
                                    </span>
                                </div>
                            </div>
                            <div class="row" v-if="wallet.mandate && wallet.mandate.id">
                                <label class="col-sm-4 col-form-label">{{ $t('user.auto-payment') }}</label>
                                <div class="col-sm-8">
                                    <span id="autopayment" :class="'form-control-plaintext' + (wallet.mandateState ? ' text-danger' : '')"
                                          v-html="$t('user.auto-payment-text', {
                                              amount: wallet.mandate.amount + ' ' + wallet.currency,
                                              balance: wallet.mandate.balance + ' ' + wallet.currency,
                                              method: wallet.mandate.method
                                          })"
                                    >
                                        <span v-if="wallet.mandateState">({{ wallet.mandateState }})</span>.
                                    </span>
                                </div>
                            </div>
                            <div class="row" v-if="wallet.providerLink">
                                <label class="col-sm-4 col-form-label">{{ capitalize(wallet.provider) }} {{ $t('form.id') }}</label>
                                <div class="col-sm-8">
                                    <span class="form-control-plaintext" v-html="wallet.providerLink"></span>
                                </div>
                            </div>
                        </form>
                        <div class="mt-2">
                            <button id="button-award" class="btn btn-success" type="button" @click="awardDialog">{{ $t('user.add-bonus') }}</button>
                            <button id="button-penalty" class="btn btn-danger" type="button" @click="penalizeDialog">{{ $t('user.add-penalty') }}</button>
                        </div>
                    </div>
                    <h2 class="card-title mt-4">{{ $t('wallet.transactions') }}</h2>
                    <transaction-log v-if="wallet.id && !walletReload" class="card-text" :wallet-id="wallet.id" :is-admin="true"></transaction-log>
                </div>
            </div>
            <div class="tab-pane" id="user-aliases" role="tabpanel" aria-labelledby="tab-aliases">
                <div class="card-body">
                    <div class="card-text">
                        <table class="table table-sm table-hover mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">{{ $t('form.email') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(alias, index) in user.aliases" :id="'alias' + index" :key="index">
                                    <td>{{ alias }}</td>
                                </tr>
                            </tbody>
                            <tfoot class="table-fake-body">
                                <tr>
                                    <td>{{ $t('user.aliases-none') }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            <div class="tab-pane" id="user-subscriptions" role="tabpanel" aria-labelledby="tab-subscriptions">
                <div class="card-body">
                    <div class="card-text">
                        <table class="table table-sm table-hover mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">{{ $t('user.subscription') }}</th>
                                    <th scope="col">{{ $t('user.price') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(sku, sku_id) in skus" :id="'sku' + sku.id" :key="sku_id">
                                    <td>{{ sku.name }}</td>
                                    <td>{{ sku.price }}</td>
                                </tr>
                            </tbody>
                            <tfoot class="table-fake-body">
                                <tr>
                                    <td colspan="2">{{ $t('user.subscriptions-none') }}</td>
                                </tr>
                            </tfoot>
                        </table>
                        <small v-if="discount > 0" class="hint">
                            <hr class="m-0">
                            &sup1; {{ $t('user.discount-hint') }}: {{ discount }}% - {{ discount_description }}
                        </small>
                        <div class="mt-2">
                            <button type="button" class="btn btn-danger" id="reset2fa" v-if="has2FA" @click="reset2FADialog">
                                {{ $t('user.reset-2fa') }}
                            </button>
                            <button type="button" class="btn btn-secondary" id="addbetasku" v-if="!hasBeta" @click="addBetaSku">
                                {{ $t('user.add-beta') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="tab-pane" id="user-domains" role="tabpanel" aria-labelledby="tab-domains">
                <div class="card-body">
                    <div class="card-text">
                        <table class="table table-sm table-hover mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">{{ $t('domain.namespace') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="domain in domains" :id="'domain' + domain.id" :key="domain.id" @click="$root.clickRecord">
                                    <td>
                                        <svg-icon icon="globe" :class="$root.domainStatusClass(domain)" :title="$root.domainStatusText(domain)"></svg-icon>
                                        <router-link :to="{ path: '/domain/' + domain.id }">{{ domain.namespace }}</router-link>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot class="table-fake-body">
                                <tr>
                                    <td>{{ $t('user.domains-none') }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            <div class="tab-pane" id="user-users" role="tabpanel" aria-labelledby="tab-users">
                <div class="card-body">
                    <div class="card-text">
                        <table class="table table-sm table-hover mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">{{ $t('form.primary-email') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in users" :id="'user' + item.id" :key="item.id" @click="$root.clickRecord">
                                    <td>
                                        <svg-icon icon="user" :class="$root.userStatusClass(item)" :title="$root.userStatusText(item)"></svg-icon>
                                        <router-link v-if="item.id != user.id" :to="{ path: '/user/' + item.id }">{{ item.email }}</router-link>
                                        <span v-else>{{ item.email }}</span>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot class="table-fake-body">
                                <tr>
                                    <td>{{ $t('user.users-none') }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            <div class="tab-pane" id="user-distlists" role="tabpanel" aria-labelledby="tab-distlists">
                <div class="card-body">
                    <div class="card-text">
                        <table class="table table-sm table-hover mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">{{ $t('distlist.name') }}</th>
                                    <th scope="col">{{ $t('form.email') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="list in distlists" :key="list.id" @click="$root.clickRecord">
                                    <td>
                                        <svg-icon icon="users" :class="$root.distlistStatusClass(list)" :title="$root.distlistStatusText(list)"></svg-icon>
                                        <router-link :to="{ path: '/distlist/' + list.id }">{{ list.name }}</router-link>
                                    </td>
                                    <td>
                                        <router-link :to="{ path: '/distlist/' + list.id }">{{ list.email }}</router-link>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot class="table-fake-body">
                                <tr>
                                    <td colspan="2">{{ $t('distlist.list-empty') }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            <div class="tab-pane" id="user-resources" role="tabpanel" aria-labelledby="tab-resources">
                <div class="card-body">
                    <div class="card-text">
                        <table class="table table-sm table-hover mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">{{ $t('form.name') }}</th>
                                    <th scope="col">{{ $t('form.email') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="resource in resources" :key="resource.id" @click="$root.clickRecord">
                                    <td>
                                        <svg-icon icon="cog" :class="$root.resourceStatusClass(resource)" :title="$root.resourceStatusText(resource)"></svg-icon>
                                        <router-link :to="{ path: '/resource/' + resource.id }">{{ resource.name }}</router-link>
                                    </td>
                                    <td>
                                        <router-link :to="{ path: '/resource/' + resource.id }">{{ resource.email }}</router-link>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot class="table-fake-body">
                                <tr>
                                    <td colspan="2">{{ $t('resource.list-empty') }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            <div class="tab-pane" id="user-shared-folders" role="tabpanel" aria-labelledby="tab-shared-folders">
                <div class="card-body">
                    <div class="card-text">
                        <table class="table table-sm table-hover mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">{{ $t('form.name') }}</th>
                                    <th scope="col">{{ $t('form.type') }}</th>
                                    <th scope="col">{{ $t('form.email') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="folder in folders" :key="folder.id" @click="$root.clickRecord">
                                    <td>
                                        <svg-icon icon="folder-open" :class="$root.folderStatusClass(folder)" :title="$root.folderStatusText(folder)"></svg-icon>
                                        <router-link :to="{ path: '/shared-folder/' + folder.id }">{{ folder.name }}</router-link>
                                    </td>
                                    <td>{{ $t('shf.type-' + folder.type) }}</td>
                                    <td><router-link :to="{ path: '/shared-folder/' + folder.id }">{{ folder.email }}</router-link></td>
                                </tr>
                            </tbody>
                            <tfoot class="table-fake-body">
                                <tr>
                                    <td colspan="3">{{ $t('shf.list-empty') }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            <div class="tab-pane" id="user-settings" role="tabpanel" aria-labelledby="tab-settings">
                <div class="card-body">
                    <div class="card-text">
                        <form class="read-only short">
                            <div class="row plaintext">
                                <label for="greylist_enabled" class="col-sm-4 col-form-label">{{ $t('user.greylisting') }}</label>
                                <div class="col-sm-8">
                                    <span class="form-control-plaintext" id="greylist_enabled">
                                        <span v-if="user.config.greylist_enabled" class="text-success">{{ $t('form.enabled') }}</span>
                                        <span v-else class="text-danger">{{ $t('form.disabled') }}</span>
                                    </span>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div id="discount-dialog" class="modal" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $t('user.discount-title') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" :aria-label="$t('btn.close')"></button>
                    </div>
                    <div class="modal-body">
                        <p>
                            <select v-model="wallet.discount_id" class="form-select">
                                <option value="">- {{ $t('form.none') }} -</option>
                                <option v-for="item in discounts" :value="item.id" :key="item.id">{{ item.label }}</option>
                            </select>
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary modal-cancel" data-bs-dismiss="modal">{{ $t('btn.cancel') }}</button>
                        <button type="button" class="btn btn-primary modal-action" @click="submitDiscount()">
                            <svg-icon icon="check"></svg-icon> {{ $t('btn.submit') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div id="email-dialog" class="modal" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $t('user.ext-email') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" :aria-label="$t('btn.close')"></button>
                    </div>
                    <div class="modal-body">
                        <p>
                            <input v-model="external_email" name="external_email" class="form-control">
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary modal-cancel" data-bs-dismiss="modal">{{ $t('btn.cancel') }}</button>
                        <button type="button" class="btn btn-primary modal-action" @click="submitEmail()">
                            <svg-icon icon="check"></svg-icon> {{ $t('btn.submit') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div id="oneoff-dialog" class="modal" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $t(oneoff_negative ? 'user.add-penalty-title' : 'user.add-bonus-title') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" :aria-label="$t('btn.close')"></button>
                    </div>
                    <div class="modal-body">
                        <form data-validation-prefix="oneoff_">
                            <div class="row mb-3">
                                <label for="oneoff_amount" class="col-form-label">{{ $t('form.amount') }}</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="oneoff_amount" v-model="oneoff_amount" required>
                                    <span class="input-group-text">{{ wallet.currency }}</span>
                                </div>
                            </div>
                            <div class="row">
                                <label for="oneoff_description" class="col-form-label">{{ $t('form.description') }}</label>
                                <input class="form-control" id="oneoff_description" v-model="oneoff_description" required>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary modal-cancel" data-bs-dismiss="modal">{{ $t('btn.cancel') }}</button>
                        <button type="button" class="btn btn-primary modal-action" @click="submitOneOff()">
                            <svg-icon icon="check"></svg-icon> {{ $t('btn.submit') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div id="reset-2fa-dialog" class="modal" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $t('user.reset-2fa-title') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" :aria-label="$t('btn.close')"></button>
                    </div>
                    <div class="modal-body">
                        <p>{{ $t('user.2fa-hint1') }}</p>
                        <p>{{ $t('user.2fa-hint2') }}</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary modal-cancel" data-bs-dismiss="modal">{{ $t('btn.cancel') }}</button>
                        <button type="button" class="btn btn-danger modal-action" @click="reset2FA()">{{ $t('btn.reset') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import { Modal } from 'bootstrap'
    import TransactionLog from '../Widgets/TransactionLog'

    export default {
        components: {
            TransactionLog
        },
        beforeRouteUpdate (to, from, next) {
            // An event called when the route that renders this component has changed,
            // but this component is reused in the new route.
            // Required to handle links from /user/XXX to /user/YYY
            next()
            this.$parent.routerReload()
        },
        data() {
            return {
                oneoff_amount: '',
                oneoff_description: '',
                oneoff_negative: false,
                discount: 0,
                discount_description: '',
                discounts: [],
                external_email: '',
                folders: [],
                has2FA: false,
                hasBeta: false,
                wallet: {},
                walletReload: false,
                distlists: [],
                domains: [],
                resources: [],
                skus: [],
                sku2FA: null,
                users: [],
                user: {
                    aliases: [],
                    config: {},
                    wallet: {},
                    skus: {},
                }
            }
        },
        created() {
            const user_id = this.$route.params.user

            this.$root.startLoading()

            axios.get('/api/v4/users/' + user_id)
                .then(response => {
                    this.$root.stopLoading()

                    this.user = response.data

                    const financesTab = '#user-finances'
                    const keys = ['first_name', 'last_name', 'external_email', 'billing_address', 'phone', 'organization']

                    let country = this.user.settings.country
                    if (country && country in window.config.countries) {
                        country = window.config.countries[country][1]
                    }

                    this.user.country = country

                    keys.forEach(key => { this.user[key] = this.user.settings[key] })

                    this.discount = this.user.wallet.discount
                    this.discount_description = this.user.wallet.discount_description

                    // TODO: currencies, multi-wallets, accounts
                    // Get more info about the wallet (e.g. payment provider related)
                    this.$root.addLoader(financesTab)
                    axios.get('/api/v4/wallets/' + this.user.wallets[0].id)
                        .then(response => {
                            this.$root.removeLoader(financesTab)
                            this.wallet = response.data
                            this.setMandateState()
                        })
                        .catch(error => {
                            this.$root.removeLoader(financesTab)
                        })

                    // Create subscriptions list
                    axios.get('/api/v4/users/' + user_id + '/skus')
                        .then(response => {
                            // "merge" SKUs with user entitlement-SKUs
                            response.data.forEach(sku => {
                                const userSku = this.user.skus[sku.id]
                                if (userSku) {
                                    let cost = userSku.costs.reduce((sum, current) => sum + current)
                                    let item = {
                                        id: sku.id,
                                        name: sku.name,
                                        cost: cost,
                                        price: this.$root.priceLabel(cost, this.discount)
                                    }

                                    if (sku.range) {
                                        item.name += ' ' + userSku.count + ' ' + sku.range.unit
                                    }

                                    this.skus.push(item)

                                    if (sku.handler == 'auth2f') {
                                        this.has2FA = true
                                        this.sku2FA = sku.id
                                    } else if (sku.handler == 'beta') {
                                        this.hasBeta = true
                                    }
                                }
                            })
                        })

                    // Fetch users
                    // TODO: Multiple wallets
                    axios.get('/api/v4/users?owner=' + user_id)
                        .then(response => {
                            this.users = response.data.list;
                        })

                    // Fetch domains
                    axios.get('/api/v4/domains?owner=' + user_id)
                        .then(response => {
                            this.domains = response.data.list
                        })

                    // Fetch distribution lists
                    axios.get('/api/v4/groups?owner=' + user_id)
                        .then(response => {
                            this.distlists = response.data.list
                        })

                    // Fetch resources lists
                    axios.get('/api/v4/resources?owner=' + user_id)
                        .then(response => {
                            this.resources = response.data.list
                        })

                    // Fetch shared folders lists
                    axios.get('/api/v4/shared-folders?owner=' + user_id)
                        .then(response => {
                            this.folders = response.data.list
                        })
                })
                .catch(this.$root.errorHandler)
        },
        mounted() {
            $(this.$el).find('ul.nav-tabs a').on('click', this.$root.tab)
        },
        methods: {
            addBetaSku() {
                axios.post('/api/v4/users/' + this.user.id + '/skus/beta')
                    .then(response => {
                        if (response.data.status == 'success') {
                            this.$toast.success(response.data.message)
                            this.hasBeta = true
                            const sku = response.data.sku
                            this.skus.push({
                                id: sku.id,
                                name: sku.name,
                                cost: sku.cost,
                                price: this.$root.priceLabel(sku.cost, this.discount)
                            })
                        }
                    })
            },
            capitalize(str) {
                return str.charAt(0).toUpperCase() + str.slice(1)
            },
            awardDialog() {
                this.oneOffDialog(false)
            },
            discountEdit() {
                if (!this.discount_dialog) {
                    const dialog = $('#discount-dialog')[0]

                    dialog.addEventListener('shown.bs.modal', e => {
                        $(dialog).find('select').focus()
                        // Note: Vue v-model is strict, convert null to a string
                        this.wallet.discount_id = this.wallet_discount_id || ''
                    })

                    this.discount_dialog = new Modal(dialog)
                }

                this.discount_dialog.show()

                if (!this.discounts.length) {
                    // Fetch discounts
                    axios.get('/api/v4/users/' + this.user.id + '/discounts')
                        .then(response => {
                            this.discounts = response.data.list
                        })
                }
            },
            emailEdit() {
                this.external_email = this.user.external_email
                this.$root.clearFormValidation($('#email-dialog'))

                if (!this.email_dialog) {
                    const dialog = $('#email-dialog')[0]

                    dialog.addEventListener('shown.bs.modal', e => {
                        $(dialog).find('input').focus()
                    })

                    this.email_dialog = new Modal(dialog)
                }

                this.email_dialog.show()
            },
            setMandateState() {
                let mandate = this.wallet.mandate
                if (mandate && mandate.id) {
                    if (!mandate.isValid) {
                        this.wallet.mandateState = mandate.isPending ? 'pending' : 'invalid'
                    } else if (mandate.isDisabled) {
                        this.wallet.mandateState = 'disabled'
                    }
                }
            },
            oneOffDialog(negative) {
                this.oneoff_negative = negative

                if (!this.oneoff_dialog) {
                    const dialog = $('#oneoff-dialog')[0]

                    dialog.addEventListener('shown.bs.modal', () => {
                        this.$root.clearFormValidation(dialog)
                        $(dialog).find('#oneoff_amount').focus()
                    })

                    this.oneoff_dialog = new Modal(dialog)
                }

                this.oneoff_dialog.show()
            },
            penalizeDialog() {
                this.oneOffDialog(true)
            },
            reload() {
                // this is to reload transaction log
                this.walletReload = true
                this.$nextTick(() => { this.walletReload = false })
            },
            reset2FA() {
                new Modal('#reset-2fa-dialog').hide()
                axios.post('/api/v4/users/' + this.user.id + '/reset2FA')
                    .then(response => {
                        if (response.data.status == 'success') {
                            this.$toast.success(response.data.message)
                            this.skus = this.skus.filter(sku => sku.id != this.sku2FA)
                            this.has2FA = false
                        }
                    })
            },
            reset2FADialog() {
                new Modal('#reset-2fa-dialog').show()
            },
            submitDiscount() {
                this.discount_dialog.hide()

                axios.put('/api/v4/wallets/' + this.user.wallets[0].id, { discount: this.wallet.discount_id })
                    .then(response => {
                        if (response.data.status == 'success') {
                            this.$toast.success(response.data.message)
                            this.wallet = Object.assign({}, this.wallet, response.data)

                            // Update prices in Subscriptions tab
                            if (this.user.wallet.id == response.data.id) {
                                this.discount = this.wallet.discount
                                this.discount_description = this.wallet.discount_description

                                this.skus.forEach(sku => {
                                    sku.price = this.$root.priceLabel(sku.cost, this.discount)
                                })
                            }
                        }
                    })
            },
            submitEmail() {
                axios.put('/api/v4/users/' + this.user.id, { external_email: this.external_email })
                    .then(response => {
                        if (response.data.status == 'success') {
                            this.email_dialog.hide()
                            this.$toast.success(response.data.message)
                            this.user.external_email = this.external_email
                            this.external_email = null // required because of Vue
                        }
                    })
            },
            submitOneOff() {
                let wallet_id = this.user.wallets[0].id
                let post = {
                    amount: this.oneoff_amount,
                    description: this.oneoff_description
                }

                if (this.oneoff_negative && /^\d+(\.?\d+)?$/.test(post.amount)) {
                    post.amount *= -1
                }

                this.$root.clearFormValidation('#oneoff-dialog')

                axios.post('/api/v4/wallets/' + wallet_id + '/one-off', post)
                    .then(response => {
                        if (response.data.status == 'success') {
                            this.oneoff_dialog.hide()
                            this.$toast.success(response.data.message)
                            this.wallet = Object.assign({}, this.wallet, {balance: response.data.balance})
                            this.oneoff_amount = ''
                            this.oneoff_description = ''
                            this.reload()
                        }
                    })
            },
            suspendUser() {
                axios.post('/api/v4/users/' + this.user.id + '/suspend', {})
                    .then(response => {
                        if (response.data.status == 'success') {
                            this.$toast.success(response.data.message)
                            this.user = Object.assign({}, this.user, { isSuspended: true })
                        }
                    })
            },
            unsuspendUser() {
                axios.post('/api/v4/users/' + this.user.id + '/unsuspend', {})
                    .then(response => {
                        if (response.data.status == 'success') {
                            this.$toast.success(response.data.message)
                            this.user = Object.assign({}, this.user, { isSuspended: false })
                        }
                    })
            }
        }
    }
</script>
