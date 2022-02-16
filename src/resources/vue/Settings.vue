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
                                        <span v-if="rule.label == 'last'" v-html="ruleLastHTML(rule)"></span>
                                        <label v-else :for="'policy-' + rule.label" class="form-check-label pe-2">{{ rule.name.split(':')[0] }}</label>
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
            ruleLastHTML(rule) {
                let parts = rule.name.split(/[0-9]+/)
                let options = [1, 2, 3, 4, 5, 6]

                options = options.map(num => {
                    let selected = num == rule.param ? ' selected' : ''
                    return `<option value="${num}"${selected}>${num}</option>`
                })

                return `<label for="policy-last" class="form-check-label pe-2">
                    ${parts[0]} <select class="form-select form-select-sm d-inline w-auto">${options.join('')}</select> ${parts[1]}
                    </label>`
            },
            submit() {
                this.$root.clearFormValidation($('#settings form'))

                let password_policy = [];

                $('#password_policy > li > input:checked').each((i, element) => {
                    let entry = element.name
                    let param = $(element.parentNode).find('select,input[type=text]').val()

                    if (param) {
                        entry += ':' + param
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
