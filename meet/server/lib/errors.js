/**
 * Error produced when a socket request has a timeout.
 */
class SocketTimeoutError extends Error {
    constructor(message) {
        super(message);

        this.name = 'SocketTimeoutError';

        // eslint-disable-next-line no-prototype-builtins
        if (Error.hasOwnProperty('captureStackTrace')) // Just in V8.
            Error.captureStackTrace(this, SocketTimeoutError);
        else
            this.stack = (new Error(message)).stack;
    }
}

module.exports =
{
    SocketTimeoutError
};
