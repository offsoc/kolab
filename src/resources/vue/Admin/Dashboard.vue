<template>
    <div class="container" dusk="dashboard-component">
        <user-search></user-search>
        <div id="dashboard-nav" class="mt-3">
            <router-link class="card link-stats" :to="{ name: 'stats' }">
                <svg-icon icon="chart-line"></svg-icon><span class="name">{{ $t('dashboard.stats') }}</span>
            </router-link>
        </div>
    </div>
</template>

<script>
    import UserSearch from '../Widgets/UserSearch'

    import { library } from '@fortawesome/fontawesome-svg-core'

    library.add(
        require('@fortawesome/free-solid-svg-icons/faChartLine').definition,
    )

    export default {
        components: {
            UserSearch
        }
    }
</script>
