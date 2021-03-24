<template>
    <div class="container" dusk="dashboard-component">
        <user-search></user-search>
        <div id="dashboard-nav" class="mt-3">
        </div>
    </div>
</template>

<script>
    import UserSearch from '../Widgets/UserSearch'

    export default {
        components: {
            UserSearch
        }
    }
</script>
