import Component, { ComponentAttrs } from '../Component';
import Button from './Button';
import listItems from '../helpers/listItems';
import extract from '../utils/extract';
import type Mithril from 'mithril';
import classList from '../utils/classList';
import app from '../app';
import Icon from './Icon';

export interface AlertAttrs extends ComponentAttrs {
  /** The type of alert this is. Will be used to give the alert a class name of `Alert--{type}`. */
  type?: string;
  /** Title of the alert. Optional. */
  title?: Mithril.Children;
  /** Icon used next to the title. Optional. */
  icon?: string;
  /** An array of controls to show in the alert. */
  controls?: Mithril.Children;
  /** Whether or not the alert can be dismissed. */
  dismissible?: boolean;
  /** A callback to run when the alert is dismissed */
  ondismiss?: Function;
  /** A class to assign to the container element */
  containerClassName?: string;
}

/**
 * The `Alert` component represents an alert box, which contains a message,
 * some controls, and may be dismissible.
 */
export default class Alert<T extends AlertAttrs = AlertAttrs> extends Component<T> {
  view(vnode: Mithril.VnodeDOM<T, this>) {
    const attrs = Object.assign({}, this.attrs);

    const type = extract(attrs, 'type');
    attrs.className = classList('Alert', `Alert--${type}`, attrs.className);

    const title = extract(attrs, 'title');
    const icon = extract(attrs, 'icon');
    const content = extract(attrs, 'content') || vnode.children;
    const controls = (extract(attrs, 'controls') || []) as Mithril.Vnode[];

    // If the alert is meant to be dismissible (which is the case by default),
    // then we will create a dismiss button to append as the final control in
    // the alert.
    const dismissible = extract(attrs, 'dismissible');
    const ondismiss = extract(attrs, 'ondismiss');
    const dismissControl: Mithril.Vnode[] = [];

    if (dismissible || dismissible === undefined) {
      dismissControl.push(
        <Button
          aria-label={app.translator.trans('core.lib.alert.dismiss_a11y_label')}
          icon="fas fa-times"
          className="Button Button--link Button--icon Alert-dismiss"
          onclick={ondismiss}
        />
      );
    }

    return (
      <div {...attrs}>
        <div className={classList('Alert-container', attrs.containerClassName)}>
          <div className="Alert-content">
            {!!title && (
              <div className="Alert-title">
                {!!icon && (
                  <span className="Alert-title-icon">
                    <Icon name={icon} />
                  </span>
                )}
                <span className="Alert-title-text">{title}</span>
              </div>
            )}
            <span className="Alert-body">{content}</span>
          </div>
          <ul className="Alert-controls">{listItems(controls.concat(dismissControl))}</ul>
        </div>
      </div>
    );
  }
}
