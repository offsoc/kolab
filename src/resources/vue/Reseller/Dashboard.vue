<template>
    <div class="container" dusk="dashboard-component">
        <user-search></user-search>
        <div id="dashboard-nav" class="mt-3">
            <router-link class="card link-invitations" :to="{ name: 'invitations' }">
                <svg-icon icon="envelope-open-text"></svg-icon><span class="name">Invitations</span>
            </router-link>
        </div>
    </div>
</template>

<script>
    import UserSearch from '../Widgets/UserSearch'
    import { library } from '@fortawesome/fontawesome-svg-core'
    import { faEnvelopeOpenText } from '@fortawesome/free-solid-svg-icons'

    library.add(faEnvelopeOpenText)

    export default {
        components: {
            UserSearch
        }
    }
</script>
