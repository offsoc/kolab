class Roles {
    static get SUBSCRIBER() { return 1 << 0; }
    static get PUBLISHER() { return 1 << 1; }
    static get MODERATOR() { return 1 << 2; }
    static get SCREEN() { return 1 << 3; }
    static get OWNER() { return 1 << 4; }
}

export { Roles }
