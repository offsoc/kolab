<template>
    <div class="container">
        <div class="card" id="settings">
            <div class="card-body">
                <div class="card-title">
                    {{ $t('dashboard.settings') }}
                </div>
                <div class="card-text">
                    <form @submit.prevent="submit">
                        <div class="row mb-3">
                            <label class="col-sm-4 col-form-label">{{ $t('user.passwordpolicy') }}</label>
                            <div class="col-sm-8">
                                <ul id="password_policy" class="list-group ms-1 mt-1">
                                    <li v-for="rule in passwordPolicy" :key="rule.label" class="list-group-item border-0 form-check pt-1 pb-1">
                                        <input type="checkbox" class="form-check-input" :id="'policy-' + rule.label" :name="rule.label" :checked="rule.enabled">
                                        <label :for="'policy-' + rule.label" class="form-check-label pe-2">{{ rule.name.split(':')[0] }}</label>
                                        <input type="text" class="form-control form-control-sm w-auto d-inline" v-if="['min', 'max'].includes(rule.label)" :value="rule.param" size="3">
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <btn class="btn-primary" type="submit" icon="check">{{ $t('btn.submit') }}</btn>
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
                passwordPolicy: []
            }
        },
        created() {
            this.wallet = this.$store.state.authInfo.wallet
        },
        mounted() {
            this.$root.startLoading()

            axios.get('/api/v4/password-policy')
                .then(response => {
                    this.$root.stopLoading()

                    if (response.data.list) {
                        this.passwordPolicy = response.data.list
                    }
                })
                .catch(this.$root.errorHandler)
        },
        methods: {
            submit() {
                this.$root.clearFormValidation($('#settings form'))

                let password_policy = [];

                $('#password_policy > li > input:checked').each((i, element) => {
                    let entry = element.name
                    const input = $(element.parentNode).find('input[type=text]')[0]

                    if (input) {
                        entry += ':' + input.value
                    }

                    password_policy.push(entry)
                })

                let post = { password_policy: password_policy.join(',') }

                axios.post('/api/v4/users/' + this.wallet.user_id + '/config', post)
                    .then(response => {
                        this.$toast.success(response.data.message)
                    })
            },
        }
    }
</script>
