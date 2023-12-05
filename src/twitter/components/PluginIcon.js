import { SVG, Path } from "@wordpress/primitives";

const TwitterIcon = (fillColor) => (
    <SVG
        version="1.1"
        id="Twitter"
        x="0px"
        y="0px"
        width="144.083px"
        height="144px"
        viewBox="0 0 144.083 144"
    >
        <Path
            d="M72.04,14.165c-31.94,0-57.833,25.894-57.833,57.834c0,31.939,25.893,57.836,57.833,57.836  c31.941,0,57.835-25.896,57.835-57.836C129.875,40.059,103.981,14.165,72.04,14.165z M100.272,57.631c0,0,1.375,6.815-3.559,18.787  c-4.936,11.972-17.15,23.297-33.974,24.349c-16.825,1.051-25.887-6.391-25.887-6.391c12.62,0.971,18.201-4.206,21.194-5.905  c-11.729-1.537-13.428-10.354-13.428-10.354c1.132,0.486,4.61,0.324,6.471-0.161c-11.485-2.831-11.728-14.398-11.728-14.398  s4.286,2.184,6.713,1.779c-11.083-8.413-4.61-19.414-4.61-19.414c14.641,16.017,30.171,15.127,30.171,15.127l0.07,0.105  c-0.282-1.128-0.434-2.308-0.434-3.523c0-8.008,6.492-14.499,14.5-14.499c4.23,0,8.037,1.813,10.688,4.703l0.092-0.052  c5.176-0.324,8.979-3.074,8.979-3.074c-1.576,4.604-4.924,6.749-6.322,7.471c0.018,0.043,0.035,0.086,0.051,0.128  c1.809-0.438,6.662-1.625,7.971-2.017C104.397,55.468,100.272,57.631,100.272,57.631z"
            fill={fillColor}
        />
    </SVG>
);

const DefaultIcon = TwitterIcon("#1B1C20");
const EnabledIcon = TwitterIcon("#1DA1F2");
const DisabledIcon = TwitterIcon("#787E88");
const FailedIcon = TwitterIcon("#D0494A");
const TweetedIcon = TwitterIcon("#7FD051");

export { DefaultIcon, EnabledIcon, DisabledIcon, FailedIcon, TweetedIcon };
