<template>
    <div class="container" dusk="rooms-component">
        <div id="meet-rooms" class="card">
            <div class="card-body">
                <div class="card-title">Voice &amp; Video Conferencing <small><sup class="badge badge-primary">beta</sup></small></div>
                <div class="card-text">
                    <p>
                        Welcome to our beta program for Voice &amp; Video Conferencing.
                    </p>
                    <p>
                        You have a room of your own at the URL below. This room is only open when you yourself are in
                        attendance. Use this URL to invite people to join you.
                    </p>
                    <p>
                        <router-link v-if="href" :to="roomRoute">{{ href }}</router-link>
                    </p>
                    <p>
                        This is a work in progress and more features will be added over time. Current features include:
                    </p>
                    <dl>
                        <dt>Screen Sharing</dt>
                        <dd>
                            Share your screen for presentations or show-and-tell.
                        </dd>

                        <dt>Room Security</dt>
                        <dd>
                            Increase the room security by setting a password that attendees will need to know
                            before they can enter, or lock the door so attendees will have to knock, and a moderator
                            can accept or deny those requests.
                        </dd>

                        <dt>Raise Hand (Q&amp;A)</dt>
                        <dd>
                            Silent audience members can raise their hand to facilitate a Question &amp; Answer session
                            with the panel members.
                        </dd>

                        <dt>Moderator Delegation</dt>
                        <dd>
                            Delegate moderator authority for the session, so that a speaker is not needlessly
                            interrupted with attendees knocking and other moderator duties.
                        </dd>

                        <dt>Eject Attendees</dt>
                        <dd>
                            Eject attendees from the session in order to force them to reconnect, or address policy
                            violations. Click the user icon for effective dismissal.
                        </dd>

                        <dt>Silent Audience Members</dt>
                        <dd>
                            For a webinar-style session, configure the room to force all new attendees to be silent
                            audience members.
                        </dd>

                        <dt>Language Specific Audio Channels</dt>
                        <dd>
                            Designate a participant to interpret the original audio to a target language, for sessions
                            with multi-lingual attendees. The interpreter is expected to be able to relay the original
                            audio, and override it.
                        </dd>
                    </dl>
                    <p>
                        Keep in mind that this is still in beta and might come with some issues.
                        Should you encounter any on your way, let us know by contacting support.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    export default {
        data() {
            return {
                rooms: [],
                href: '',
                roomRoute: ''
            }
        },
        mounted() {
            if (!this.$root.hasSKU('meet')) {
                this.$root.errorPage(403)
                return
            }

            this.$root.startLoading()

            axios.get('/api/v4/openvidu/rooms')
                .then(response => {
                    this.$root.stopLoading()

                    this.rooms = response.data.list
                    if (response.data.count) {
                        this.roomRoute = '/meet/' + encodeURI(this.rooms[0].name)
                        this.href = window.config['app.url'] + this.roomRoute
                    }
                })
                .catch(this.$root.errorHandler)
        }
    }
</script>
