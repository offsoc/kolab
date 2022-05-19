<template>
    <div class="container">
        <div class="card" id="domain-list">
            <div class="card-body">
                <div class="card-title">
                    {{ $t('user.domains') }}
                    <btn-router v-if="!$root.isDegraded()" class="btn-success float-end" to="domain/new" icon="globe">
                        {{ $t('domain.create') }}
                    </btn-router>
                </div>
                <div class="card-text">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th scope="col">{{ $t('domain.namespace') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="domain in domains" :key="domain.id" @click="$root.clickRecord">
                                <td>
                                    <svg-icon icon="globe" :class="$root.statusClass(domain)" :title="$root.statusText(domain)"></svg-icon>
                                    <router-link :to="{ path: 'domain/' + domain.id }">{{ domain.namespace }}</router-link>
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
    </div>
</template>

<script>
    import { library } from '@fortawesome/fontawesome-svg-core'

    library.add(
        require('@fortawesome/free-solid-svg-icons/faGlobe').definition,
    )

    export default {
        data() {
            return {
                domains: []
            }
        },
        created() {
            axios.get('/api/v4/domains', { loader: true })
                .then(response => {
                    this.domains = response.data
                })
                .catch(this.$root.errorHandler)
        }
    }
</script>
