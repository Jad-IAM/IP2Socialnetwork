import Modal, { IInternalModalAttrs } from '../../common/components/Modal';
export interface ILoadingModalAttrs extends IInternalModalAttrs {
}
export default class LoadingModal<ModalAttrs extends ILoadingModalAttrs = ILoadingModalAttrs> extends Modal<ModalAttrs> {
    protected static readonly isDismissibleViaCloseButton: boolean;
    protected static readonly isDismissibleViaEscKey: boolean;
    protected static readonly isDismissibleViaBackdropClick: boolean;
    className(): string;
    title(): string | any[];
    content(): null;
}
