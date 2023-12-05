import { registerStore } from "@wordpress/data";

import reducer from "./reducer";
import * as actions from "./actions";
import * as selectors from "./selectors";

export const STORE = "rrze/autoshare";

export function createAutoshareStore() {
    const store = registerStore(STORE, { reducer, actions, selectors });
    return store;
}
