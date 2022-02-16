<template>
    <div class="container">
        <div class="card" id="resource-list">
            <div class="card-body">
                <div class="card-title">
                    {{ $tc('resource.list-title', 2) }}
                    <small><sup class="badge bg-primary">{{ $t('dashboard.beta') }}</sup></small>
                    <btn-router v-if="!$root.isDegraded()" to="resource/new" class="btn-success float-end" icon="cog">
                        {{ $t('resource.create') }}
                    </btn-router>
                </div>
                <div class="card-text">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th scope="col">{{ $t('form.name') }}</th>
                                <th scope="col">{{ $t('form.email') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="resource in resources" :key="resource.id" @click="$root.clickRecord">
                                <td>
                                    <svg-icon icon="cog" :class="$root.statusClass(resource)" :title="$root.statusText(resource)"></svg-icon>
                                    <router-link :to="{ path: 'resource/' + resource.id }">{{ resource.name }}</router-link>
                                </td>
                                <td>
                                    <router-link :to="{ path: 'resource/' + resource.id }">{{ resource.email }}</router-link>
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
    </div>
</template>

<script>
    export default {
        data() {
            return {
                resources: []
            }
        },
        created() {
            this.$root.startLoading()

            axios.get('/api/v4/resources')
                .then(response => {
                    this.$root.stopLoading()
                    this.resources = response.data
                })
                .catch(this.$root.errorHandler)
        }
    }
</script>
