<template>
    <div class="container" dusk="dashboard-component">
        <user-search></user-search>
        <div id="dashboard-nav" class="mt-3">
            <router-link class="card link-invitations" :to="{ name: 'invitations' }">
                <svg-icon icon="envelope-open-text"></svg-icon><span class="name">Invitations</span>
            </router-link>
            <router-link class="card link-stats" :to="{ name: 'stats' }">
                <svg-icon icon="chart-line"></svg-icon><span class="name">Stats</span>
            </router-link>
        </div>
    </div>
</template>

<script>
    import UserSearch from '../Widgets/UserSearch'
    import { library } from '@fortawesome/fontawesome-svg-core'
    import { faChartLine,faEnvelopeOpenText } from '@fortawesome/free-solid-svg-icons'

    library.add(faChartLine, faEnvelopeOpenText)

    export default {
        components: {
            UserSearch
        }
    }
</script>
