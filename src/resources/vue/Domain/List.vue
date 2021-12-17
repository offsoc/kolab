<template>
    <div class="container">
        <div class="card" id="domain-list">
            <div class="card-body">
                <div class="card-title">
                    {{ $t('user.domains') }}
                    <router-link v-if="!$root.isDegraded()" class="btn btn-success float-end create-domain" :to="{ path: 'domain/new' }" tag="button">
                        <svg-icon icon="globe"></svg-icon> {{ $t('domain.create') }}
                    </router-link>
                </div>
                <div class="card-text">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th scope="col">{{ $t('domain.namespace') }}</th>
                                <th scope="col"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="domain in domains" :key="domain.id" @click="$root.clickRecord">
                                <td>
                                    <svg-icon icon="globe" :class="$root.domainStatusClass(domain)" :title="$root.domainStatusText(domain)"></svg-icon>
                                    <router-link :to="{ path: 'domain/' + domain.id }">{{ domain.namespace }}</router-link>
                                </td>
                                <td class="buttons"></td>
                            </tr>
                        </tbody>
                        <tfoot class="table-fake-body">
                            <tr>
                                <td colspan="2">{{ $t('user.domains-none') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    export default {
        data() {
            return {
                domains: []
            }
        },
        created() {
            this.$root.startLoading()

            axios.get('/api/v4/domains')
                .then(response => {
                    this.$root.stopLoading()
                    this.domains = response.data
                })
                .catch(this.$root.errorHandler)
        }
    }
</script>
