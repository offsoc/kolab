<template>
    <div class="container">
        <div class="card" id="user-profile">
            <div class="card-body">
                <div class="card-title">Your profile</div>
                <div class="card-text">
                    <form @submit.prevent="submit">
                        <div class="form-group row plaintext">
                            <label class="col-sm-4 col-form-label">Customer No.</label>
                            <div class="col-sm-8">
                                <span class="form-control-plaintext" id="userid">{{ user_id }}</span>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="first_name" class="col-sm-4 col-form-label">First name</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" id="first_name" v-model="profile.first_name">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="last_name" class="col-sm-4 col-form-label">Last name</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" id="last_name" v-model="profile.last_name">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="organization" class="col-sm-4 col-form-label">Organization</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" id="organization" v-model="profile.organization">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="phone" class="col-sm-4 col-form-label">Phone</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" id="phone" v-model="profile.phone">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="external_email" class="col-sm-4 col-form-label">External email</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" id="external_email" v-model="profile.external_email">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="billing_address" class="col-sm-4 col-form-label">Address</label>
                            <div class="col-sm-8">
                                <textarea class="form-control" id="billing_address" rows="3" v-model="profile.billing_address"></textarea>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="country" class="col-sm-4 col-form-label">Country</label>
                            <div class="col-sm-8">
                                <select class="form-control custom-select" id="country" v-model="profile.country">
                                    <option value="">-</option>
                                    <option v-for="(item, code) in countries" :value="code" :key="code">{{ item[1] }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="password" class="col-sm-4 col-form-label">Password</label>
                            <div class="col-sm-8">
                                <input type="password" class="form-control" id="password" v-model="profile.password">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="password_confirmaton" class="col-sm-4 col-form-label">Confirm password</label>
                            <div class="col-sm-8">
                                <input type="password" class="form-control" id="password_confirmation" v-model="profile.password_confirmation">
                            </div>
                        </div>
                        <button class="btn btn-primary button-submit" type="submit"><svg-icon icon="check"></svg-icon> Submit</button>
                        <router-link
                            v-if="$root.isController(wallet_id)"
                            class="btn btn-danger button-delete"
                            to="/profile/delete" tag="button"
                        ><svg-icon icon="trash-alt"></svg-icon> Delete account</router-link>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    export default {
        data() {
            return {
                profile: {},
                user_id: null,
                wallet_id: null,
                countries: window.config.countries
            }
        },
        created() {
            this.wallet_id = this.$store.state.authInfo.wallet.id
            this.profile = this.$store.state.authInfo.settings
            this.user_id = this.$store.state.authInfo.id
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
                        delete this.profile.password_confirm

                        this.$toast.success(response.data.message)
                        this.$router.push({ name: 'dashboard' })
                    })
            }
        }
    }
</script>
