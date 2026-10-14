import { replaceable, useSkin } from '@/themes/skins';

export interface PageFooterProps {
    className?: string;
}

/** The copyright line below each page's content. Replace it with a component that renders nothing to drop it. */
function CorePageFooter({ className }: PageFooterProps) {
    const ui = useSkin('PageFooter', {});

    return (
        <div {...ui('root', className)}>
            <p {...ui('text')}>
                <a rel='noopener noreferrer' href='https://www.jifercraft.com/' target='_blank' {...ui('link')}>
                    JiferCloud Hosting&reg;
                </a>
                &nbsp;&copy; 2020 - <span suppressHydrationWarning>{new Date().getFullYear()}</span>
                <br />
                Expansión de JiferCraft Studios
            </p>
        </div>
    );
}

const PageFooter = replaceable('PageFooter', CorePageFooter);

export default PageFooter;
