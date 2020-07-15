/**
 * Application code for the Meet UI
 */

import routes from './routes-meet.js'

window.routes = routes
window.isAdmin = false

require('./app')

// Register additional icons
import { library } from '@fortawesome/fontawesome-svg-core'

import {
    faDesktop,
    faExpand,
    faMicrophone,
    faPowerOff,
    faVideo
} from '@fortawesome/free-solid-svg-icons'

// Register only these icons we need
library.add(
    faDesktop,
    faExpand,
    faMicrophone,
    faPowerOff,
    faVideo
)
