import React, { useEffect } from 'react';
import ContentContainer from '@/components/elements/ContentContainer';
import { cn } from '@/lib/cn';

export interface PageContentBlockProps {
    title?: string;
    className?: string;
    children?: React.ReactNode;
}

const PageContentBlock = ({ title, className, children }: PageContentBlockProps) => {
    useEffect(() => {
        document.title = title || document.title;
    }, [title]);

    return (
        <div>
            <ContentContainer className={cn('my-4 sm:my-6', className)}>{children}</ContentContainer>
            <ContentContainer className='mb-4'>
                <p className='text-center text-muted-foreground text-xs'>
                    <a
                        rel='noopener noreferrer'
                        href='https://www.jifercraft.com/'
                        target='_blank'
                        className='no-underline text-muted-foreground hover:text-muted-foreground'
                    >
                        JiferCloud Hosting&reg;
                    </a>
                    &nbsp;&copy; 2020 - <span suppressHydrationWarning>{new Date().getFullYear()}</span>
                    <br />
                    Expansión de JiferCraft Studios
                </p>
            </ContentContainer>
        </div>
    );
};

export default PageContentBlock;
