<template>
    <div class="container">
        <div class="card" id="user-profile">
            <div class="card-body">
                <div class="card-title">
                    {{ $t('user.profile-title') }}
                    <btn-router v-if="$root.isController(wallet.id)" to="profile/delete" class="btn-outline-danger float-end" icon="trash-alt">
                        {{ $t('user.profile-delete') }}
                    </btn-router>
                </div>
                <div class="card-text">
                    <form @submit.prevent="submit">
                        <div class="row mb-3 plaintext">
                            <label class="col-sm-4 col-form-label">{{ $t('user.custno') }}</label>
                            <div class="col-sm-8">
                                <span class="form-control-plaintext" id="userid">{{ user_id }}</span>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label for="first_name" class="col-sm-4 col-form-label">{{ $t('form.firstname') }}</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" id="first_name" v-model="profile.first_name">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label for="last_name" class="col-sm-4 col-form-label">{{ $t('form.lastname') }}</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" id="last_name" v-model="profile.last_name">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label for="organization" class="col-sm-4 col-form-label">{{ $t('user.org') }}</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" id="organization" v-model="profile.organization">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label for="phone" class="col-sm-4 col-form-label">{{ $t('form.phone') }}</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" id="phone" v-model="profile.phone">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label for="external_email" class="col-sm-4 col-form-label">{{ $t('user.ext-email') }}</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" id="external_email" v-model="profile.external_email">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label for="billing_address" class="col-sm-4 col-form-label">{{ $t('user.address') }}</label>
                            <div class="col-sm-8">
                                <textarea class="form-control" id="billing_address" rows="3" v-model="profile.billing_address"></textarea>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label for="country" class="col-sm-4 col-form-label">{{ $t('user.country') }}</label>
                            <div class="col-sm-8">
                                <select class="form-select" id="country" v-model="profile.country">
                                    <option value="">-</option>
                                    <option v-for="(item, code) in countries" :value="code" :key="code">{{ item[1] }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label for="password" class="col-sm-4 col-form-label">{{ $t('form.password') }}</label>
                            <password-input class="col-sm-8" v-model="profile"></password-input>
                        </div>
                        <btn class="btn-primary button-submit mt-2" type="submit" icon="check">{{ $t('btn.submit') }}</btn>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import PasswordInput from '../Widgets/PasswordInput'

    export default {
        components: {
            PasswordInput
        },
        data() {
            return {
                profile: {},
                user_id: null,
                wallet: {},
                countries: window.config.countries
            }
        },
        created() {
            const authInfo = this.$root.authInfo
            this.wallet = authInfo.wallet
            this.profile = authInfo.settings
            this.user_id = authInfo.id
        },
        mounted() {
            $('#first_name').focus()
        },
        methods: {
            submit() {
                this.$root.clearFormValidation($('#user-profile form'))

                axios.put('/api/v4/users/' + this.user_id, this.profile)
                    .then(response => {
                        delete this.profile.password
                        delete this.profile.password_confirmation

                        this.$toast.success(response.data.message)
                        this.$router.push({ name: 'dashboard' })
                    })
            }
        }
    }
</script>
