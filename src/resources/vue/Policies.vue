<template>
    <div class="container">
        <div class="card" id="policies">
            <div class="card-body">
                <div class="card-title">
                    {{ $t('dashboard.policies') }}
                </div>
                <div class="card-text">
                    <tabs class="mt-3" :tabs="tabs" ref="tabs"></tabs>
                    <div class="tab-content">
                        <div class="tab-pane active" id="password" role="tabpanel" aria-labelledby="tab-password">
                            <form class="card-body" @submit.prevent="submitPassword">
                                <div class="row mb-3">
                                    <label class="col-sm-4 col-form-label">{{ $t('policies.password-policy') }}</label>
                                    <div class="col-sm-8">
                                        <ul id="password_policy" class="list-group ms-1 mt-1">
                                            <li v-for="rule in passwordPolicy" :key="rule.label" class="list-group-item border-0 form-check pt-1 pb-1">
                                                <input type="checkbox" class="form-check-input"
                                                       :id="'policy-' + rule.label"
                                                       :name="rule.label"
                                                       :checked="rule.enabled || isRequired(rule)"
                                                       :disabled="isRequired(rule)"
                                                >
                                                <span v-if="rule.label == 'last'" v-html="ruleLastHTML(rule)"></span>
                                                <label v-else :for="'policy-' + rule.label" class="form-check-label pe-2" style="opacity: 1;">{{ rule.name.split(':')[0] }}</label>
                                                <input type="text" class="form-control form-control-sm w-auto d-inline" v-if="['min', 'max'].includes(rule.label)" :value="rule.param" size="3">
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label class="col-sm-4 col-form-label">{{ $t('policies.password-retention') }}</label>
                                    <div class="col-sm-8">
                                        <ul id="password_retention" class="list-group ms-1 mt-1">
                                            <li class="list-group-item border-0 form-check pt-1 pb-1">
                                                <input type="checkbox" class="form-check-input" id="max_password_age" :checked="config.max_password_age">
                                                <label for="max_password_age" class="form-check-label pe-2">{{ $t('policies.password-max-age') }}</label>
                                                <select class="form-select form-select-sm d-inline w-auto" id="max_password_age_value">
                                                    <option v-for="num in [3, 6, 9, 12]" :key="num" :value="num" :selected="num == config.max_password_age">
                                                        {{ num }} {{ $t('form.months') }}
                                                    </option>
                                                </select>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                                <btn class="btn-primary" type="submit" icon="check">{{ $t('btn.submit') }}</btn>
                            </form>
                        </div>
                        <div class="tab-pane" id="mailDelivery" role="tabpanel" aria-labelledby="tab-mailDelivery">
                            <form class="card-body" @submit.prevent="submitMailDelivery">
                                <div class="row checkbox mb-3">
                                    <label for="greylist_policy" class="col-sm-4 col-form-label">{{ $t('policies.greylist') }}</label>
                                    <div class="col-sm-8 pt-2">
                                        <input type="checkbox" id="greylist_policy" name="greylist" value="1" class="form-check-input d-block mb-2" :checked="config.greylist_policy">
                                        <small id="itip-hint" class="text-muted">
                                            {{ $t('policies.greylist-text') }}
                                        </small>
                                    </div>
                                </div>
                                <div class="row checkbox mb-3">
                                    <label for="itip_policy" class="col-sm-4 col-form-label">{{ $t('policies.calinvitations') }}</label>
                                    <div class="col-sm-8 pt-2">
                                        <input type="checkbox" id="itip_policy" name="itip" value="1" class="form-check-input d-block mb-2" :checked="config.itip_policy">
                                        <small id="itip-hint" class="text-muted">
                                            {{ $t('policies.calinvitations-text') }}
                                        </small>
                                    </div>
                                </div>
                                <div class="row checkbox mb-3">
                                    <label for="externalsender_policy" class="col-sm-4 col-form-label">{{ $t('policies.extsender') }}</label>
                                    <div class="col-sm-8 pt-2">
                                        <input type="checkbox" id="externalsender_policy" name="externalsender" value="1" class="form-check-input d-block mb-2" :checked="config.externalsender_policy">
                                        <small id="externalsender-hint" class="text-muted">
                                            {{ $t('policies.extsender-text') }}
                                        </small>
                                    </div>
                                </div>
                                <btn class="btn-primary" type="submit" icon="check">{{ $t('btn.submit') }}</btn>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    const POLICY_TYPES = ['password', 'mailDelivery']

    export default {
        data() {
            return {
                config: [],
                mailDeliveryPolicy: [],
                passwordPolicy: []
            }
        },
        computed: {
            tabs: function () {
                return POLICY_TYPES.filter(v => this[v + 'Policy'].length > 0)
                    .map(v => 'policies.' + v);
            }
        },
        created() {
            this.wallet = this.$root.authInfo.wallet
        },
        mounted() {
            axios.get('/api/v4/policies', { loader: true })
                .then(response => {
                    if (response.data.config) {
                        this.config = response.data.config
                        POLICY_TYPES.forEach(element => this[element + 'Policy'] = response.data[element])
                    }
                })
                .catch(this.$root.errorHandler)
        },
        methods: {
            isRequired(rule) {
                return rule.label == 'min' || rule.label == 'max'
            },
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
            submitMailDelivery() {
                this.$root.clearFormValidation('#maildelivery form')

                let post = {}
                this.mailDeliveryPolicy.forEach(element => post[element] = $('#' + element)[0].checked)

                axios.post('/api/v4/users/' + this.wallet.user_id + '/config', post)
                    .then(response => {
                        this.$toast.success(response.data.message)
                    })
            },
            submitPassword() {
                this.$root.clearFormValidation($('#password form'))

                let max_password_age = $('#max_password_age:checked').length ? $('#max_password_age_value').val() : 0
                let password_policy = [];

                $('#password_policy > li > input:checked').each((i, element) => {
                    let entry = element.name
                    let param = $(element.parentNode).find('select,input[type=text]').val()

                    if (param) {
                        entry += ':' + param
                    }

                    password_policy.push(entry)
                })

                let post = {
                    max_password_age,
                    password_policy: password_policy.join(','),
                }

                axios.post('/api/v4/users/' + this.wallet.user_id + '/config', post)
                    .then(response => {
                        this.$toast.success(response.data.message)
                    })
            },
        }
    }
</script>
